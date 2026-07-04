<?php

use App\Models\Client;
use App\Models\Proposal;

function validProposalPayload(array $overrides = []): array
{
    return array_merge([
        'client_id' => Client::factory()->create()->id,
        'product' => 'Plano Ouro',
        'monthly_value' => 199.90,
        'origin' => 'APP',
    ], $overrides);
}

test('cria uma proposta com dados válidos', function () {
    $response = $this->postJson('/api/v1/propostas', validProposalPayload());

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
    ]));

    $response->assertCreated()
        ->assertJsonPath('data.status', 'DRAFT')
        ->assertJsonPath('data.version', 1);

    $this->assertDatabaseHas('proposals', [
        'id' => $response->json('data.id'),
        'status' => 'DRAFT',
        'version' => 1,
    ]);
});

test('rejeita cliente inexistente', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload(['client_id' => 999999]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['client_id']);
});

test('rejeita origem inválida', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload(['origin' => 'EMAIL']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origin']);
});

test('rejeita valor mensal não positivo', function () {
    $this->postJson('/api/v1/propostas', validProposalPayload(['monthly_value' => 0]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['monthly_value']);
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
