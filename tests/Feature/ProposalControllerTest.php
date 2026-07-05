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
        ->assertJsonValidationErrors(['origin' => 'A origem selecionada é inválida.']);
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

test('retorna 404 para identificador não numérico na consulta', function () {
    $this->getJson('/api/v1/propostas/abc')
        ->assertNotFound();
});

test('retorna 404 para proposta excluída logicamente', function () {
    $proposal = Proposal::factory()->create();
    $proposal->delete();

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertNotFound();
});

test('não aceita PUT na atualização de proposta', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);

    $this->putJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'Via PUT',
    ])->assertStatus(405);
});

test('atualiza campos de uma proposta em rascunho e incrementa a versão', function () {
    $proposal = Proposal::factory()->create(['product' => 'Antigo', 'version' => 1]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'Novo Plano',
        'monthly_value' => 350.00,
    ])
        ->assertOk()
        ->assertJsonPath('data.product', 'Novo Plano')
        ->assertJsonPath('data.version', 2);

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'product' => 'Novo Plano',
        'version' => 2,
    ]);
});

test('rejeita atualização com versão desatualizada', function () {
    $proposal = Proposal::factory()->create(['product' => 'Original', 'version' => 3]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 2,
        'product' => 'Tentativa',
    ])->assertStatus(409);

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'product' => 'Original',
        'version' => 3,
    ]);
});

test('exige a versão para atualizar', function () {
    $proposal = Proposal::factory()->create();

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'product' => 'Sem versão',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['version']);
});

test('exige ao menos um campo editável na atualização', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['product', 'monthly_value', 'origin']);
});

test('rejeita valor mensal inválido na atualização', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'monthly_value' => 0,
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['monthly_value']);
});

test('rejeita origem inválida na atualização', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'origin' => 'EMAIL',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['origin' => 'A origem selecionada é inválida.']);
});

test('não permite editar proposta fora do rascunho', function () {
    $proposal = Proposal::factory()->submitted()->create(['version' => 1]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'Não pode',
    ])->assertUnprocessable();

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'SUBMITTED',
        'version' => 1,
    ]);
});

test('ignora status e client_id enviados no corpo ao atualizar', function () {
    $client = Client::factory()->create();
    $proposal = Proposal::factory()->create(['client_id' => $client->id, 'version' => 1]);
    $otherClient = Client::factory()->create();

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'Atualizado',
        'status' => 'APPROVED',
        'client_id' => $otherClient->id,
    ])
        ->assertOk()
        ->assertJsonPath('data.status', 'DRAFT');

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'DRAFT',
        'client_id' => $client->id,
    ]);
});

test('retorna 404 ao atualizar proposta inexistente', function () {
    $this->patchJson('/api/v1/propostas/999999', [
        'version' => 1,
        'product' => 'X',
    ])->assertNotFound();
});

test('retorna 404 ao atualizar proposta excluída logicamente', function () {
    $proposal = Proposal::factory()->create();
    $proposal->delete();

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'X',
    ])->assertNotFound();
});
