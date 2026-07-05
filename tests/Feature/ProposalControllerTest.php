<?php

use App\Models\Client;
use App\Models\Proposal;
use Illuminate\Support\Str;

function validProposalPayload(array $overrides = []): array
{
    return array_merge([
        'client_id' => Client::factory()->create()->id,
        'product' => 'Plano Ouro',
        'monthly_value' => 199.90,
        'origin' => 'APP',
    ], $overrides);
}

function idempotencyHeader(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
}

test('cria uma proposta com dados válidos', function () {
    $response = $this->postJson('/api/v1/propostas', validProposalPayload(), idempotencyHeader());

    $response->assertCreated()
        ->assertJsonPath('data.product', 'Plano Ouro')
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.version', 1);

    $this->assertDatabaseHas('proposals', [
        'id' => $response->json('data.id'),
        'status' => 'DRAFT',
        'version' => 1,
    ]);
});

test('ignora status e version enviados no corpo', function () {
    $response = $this->postJson('/api/v1/propostas', validProposalPayload([
        'status' => 'APPROVED',
        'version' => 99,
    ]), idempotencyHeader());

    $response->assertCreated()
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.version', 1);

    $this->assertDatabaseHas('proposals', [
        'id' => $response->json('data.id'),
        'status' => 'DRAFT',
        'version' => 1,
    ]);
});

test('exige o cabeçalho Idempotency-Key na criação', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);

    $this->assertDatabaseCount('proposals', 0);
});

test('rejeita cliente inexistente', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload(['client_id' => 999999]), idempotencyHeader())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id']);
});

test('rejeita origem inválida', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload(['origin' => 'EMAIL']), idempotencyHeader())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origin']);
});

test('rejeita valor mensal não positivo', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload(['monthly_value' => 0]), idempotencyHeader())
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['monthly_value']);
});

test('reaproveita a resposta ao repetir a mesma Idempotency-Key', function () {
    $payload = validProposalPayload();
    $headers = idempotencyHeader('chave-abc-123');

    $first = $this->postJson('/api/v1/propostas', $payload, $headers)->assertCreated();
    $second = $this->postJson('/api/v1/propostas', $payload, $headers)->assertCreated();

    expect($second->json('data.id'))->toBe($first->json('data.id'));
    $this->assertDatabaseCount('proposals', 1);
});

test('cria propostas distintas para Idempotency-Keys diferentes', function () {
    $payload = validProposalPayload();

    $this->postJson('/api/v1/propostas', $payload, idempotencyHeader('chave-1'))->assertCreated();
    $this->postJson('/api/v1/propostas', $payload, idempotencyHeader('chave-2'))->assertCreated();

    $this->assertDatabaseCount('proposals', 2);
});

test('rejeita a mesma Idempotency-Key com payload diferente', function () {
    $headers = idempotencyHeader('chave-conflito');

    $this->postJson('/api/v1/propostas', validProposalPayload(['product' => 'Plano Ouro']), $headers)
        ->assertCreated();

    $this->postJson('/api/v1/propostas', validProposalPayload(['product' => 'Plano Prata']), $headers)
        ->assertStatus(409);

    $this->assertDatabaseCount('proposals', 1);
});

test('não persiste a Idempotency-Key quando a validação falha', function () {
    $headers = idempotencyHeader('chave-invalida');

    $this->postJson('/api/v1/propostas', validProposalPayload(['monthly_value' => 0]), $headers)
        ->assertUnprocessable();

    $this->postJson('/api/v1/propostas', validProposalPayload(), $headers)
        ->assertCreated();

    $this->assertDatabaseCount('proposals', 1);
});

test('lista propostas paginadas com metadados', function () {
    Proposal::factory()->count(3)->create();

    $this->getJson('/api/v1/propostas')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonPath('meta.total', 3)
        ->assertJsonPath('meta.per_page', 15)
        ->assertJsonPath('meta.current_page', 1);
});

test('respeita o parâmetro per_page e navega entre páginas', function () {
    Proposal::factory()->count(5)->create();

    $this->getJson('/api/v1/propostas?per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 3);

    $this->getJson('/api/v1/propostas?per_page=2&page=3')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('meta.current_page', 3);
});

test('limita o per_page ao máximo permitido', function () {
    Proposal::factory()->count(2)->create();

    $this->getJson('/api/v1/propostas?per_page=999')
        ->assertOk()
        ->assertJsonPath('meta.per_page', 100);
});

test('ordena as propostas da mais recente para a mais antiga', function () {
    $older = Proposal::factory()->create();
    $newer = Proposal::factory()->create();

    $this->getJson('/api/v1/propostas')
        ->assertOk()
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

test('não lista propostas excluídas logicamente', function () {
    $visible = Proposal::factory()->create();
    Proposal::factory()->create()->delete();

    $this->getJson('/api/v1/propostas')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.id', $visible->id);
});

test('retorna uma proposta existente', function () {
    $proposal = Proposal::factory()->create();

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $proposal->id)
        ->assertJsonPath('data.status', $proposal->status->value);
});

test('retorna 404 para proposta inexistente', function () {
    $this->getJson('/api/v1/propostas/999999')
        ->assertNotFound();
});

test('retorna 404 para proposta excluída logicamente', function () {
    $proposal = Proposal::factory()->create();
    $proposal->delete();

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertNotFound();
});
