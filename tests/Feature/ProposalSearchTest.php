<?php

use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Proposal;

test('filtra propostas por status', function () {
    Proposal::factory()->submitted()->create();
    Proposal::factory()->approved()->create();

    $this->getJson('/api/v1/propostas?status=SUBMITTED')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'SUBMITTED');
});

test('filtra propostas por origem', function () {
    Proposal::factory()->create(['origin' => 'APP']);
    Proposal::factory()->create(['origin' => 'SITE']);

    $this->getJson('/api/v1/propostas?origin=SITE')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.origin', 'SITE');
});

test('filtra propostas por cliente', function () {
    $client = Client::factory()->create();
    Proposal::factory()->for($client)->create();
    Proposal::factory()->create();

    $this->getJson("/api/v1/propostas?client_id={$client->id}")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.client_id', $client->id);
});

test('filtra propostas por produto de forma parcial', function () {
    Proposal::factory()->create(['product' => 'Plano Ouro Premium']);
    Proposal::factory()->create(['product' => 'Plano Prata']);

    $this->getJson('/api/v1/propostas?product=Ouro')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.product', 'Plano Ouro Premium');
});

test('filtra propostas por faixa de valor mensal', function () {
    Proposal::factory()->create(['monthly_value' => 100.00]);
    Proposal::factory()->create(['monthly_value' => 500.00]);
    Proposal::factory()->create(['monthly_value' => 900.00]);

    $this->getJson('/api/v1/propostas?min_value=200&max_value=800')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.monthly_value', '500.00');
});

test('combina múltiplos filtros', function () {
    $client = Client::factory()->create();
    Proposal::factory()->for($client)->submitted()->create(['monthly_value' => 300.00]);
    Proposal::factory()->for($client)->submitted()->create(['monthly_value' => 1000.00]);
    Proposal::factory()->submitted()->create(['monthly_value' => 300.00]);

    $this->getJson("/api/v1/propostas?client_id={$client->id}&status=SUBMITTED&max_value=500")
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.monthly_value', '300.00');
});

test('ordena por valor mensal crescente', function () {
    Proposal::factory()->create(['monthly_value' => 500.00]);
    Proposal::factory()->create(['monthly_value' => 100.00]);
    Proposal::factory()->create(['monthly_value' => 900.00]);

    $this->getJson('/api/v1/propostas?sort=monthly_value&order=asc')
        ->assertOk()
        ->assertJsonPath('data.0.monthly_value', '100.00')
        ->assertJsonPath('data.1.monthly_value', '500.00')
        ->assertJsonPath('data.2.monthly_value', '900.00');
});

test('ordena por valor mensal decrescente', function () {
    Proposal::factory()->create(['monthly_value' => 500.00]);
    Proposal::factory()->create(['monthly_value' => 100.00]);
    Proposal::factory()->create(['monthly_value' => 900.00]);

    $this->getJson('/api/v1/propostas?sort=monthly_value&order=desc')
        ->assertOk()
        ->assertJsonPath('data.0.monthly_value', '900.00')
        ->assertJsonPath('data.2.monthly_value', '100.00');
});

test('rejeita coluna de ordenação não permitida', function () {
    $this->getJson('/api/v1/propostas?sort=version')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['sort']);
});

test('rejeita direção de ordenação inválida', function () {
    $this->getJson('/api/v1/propostas?order=ascending')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['order']);
});

test('rejeita status inválido no filtro', function () {
    $this->getJson('/api/v1/propostas?status=INVALIDO')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['status' => 'O status selecionado é inválido.']);
});

test('rejeita origem inválida no filtro', function () {
    $this->getJson('/api/v1/propostas?origin=INVALIDO')
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['origin' => 'O origem selecionado é inválido.']);
});

test('aplica filtros com ordenação e paginação em conjunto', function () {
    Proposal::factory()->count(3)->submitted()->create(['monthly_value' => 200.00]);
    Proposal::factory()->count(2)->submitted()->create(['monthly_value' => 800.00]);
    Proposal::factory()->count(4)->approved()->create();

    $response = $this->getJson('/api/v1/propostas?status=SUBMITTED&sort=monthly_value&order=desc&per_page=2')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.total', 5)
        ->assertJsonPath('meta.per_page', 2)
        ->assertJsonPath('meta.last_page', 3)
        ->assertJsonPath('data.0.monthly_value', '800.00');

    expect($response->json('data'))->each->toHaveKey('status', ProposalStatus::Submitted->value);
});
