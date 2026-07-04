<?php

use App\Models\Client;

test('cria um cliente com dados válidos', function () {
    $payload = [
        'name' => 'Maria Silva',
        'email' => 'maria@example.com',
        'document' => '52998224725',
    ];

    $response = $this->postJson('/api/v1/clients', $payload);

    $response->assertCreated()
        ->assertJsonPath('data.name', 'Maria Silva')
        ->assertJsonPath('data.email', 'maria@example.com')
        ->assertJsonPath('data.document', '52998224725');

    $this->assertDatabaseHas('clients', [
        'email' => 'maria@example.com',
        'document' => '52998224725',
    ]);
});

test('normaliza o documento removendo a máscara', function () {
    $this->postJson('/api/v1/clients', [
        'name' => 'João Souza',
        'email' => 'joao@example.com',
        'document' => '529.982.247-25',
    ])->assertCreated();

    $this->assertDatabaseHas('clients', ['document' => '52998224725']);
});

test('rejeita criação sem os campos obrigatórios', function () {
    $this->postJson('/api/v1/clients', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['name', 'email', 'document']);
});

test('rejeita documento inválido', function () {
    $this->postJson('/api/v1/clients', [
        'name' => 'Ana',
        'email' => 'ana@example.com',
        'document' => '12345678900',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['document']);
});

test('rejeita e-mail duplicado', function () {
    Client::factory()->create(['email' => 'dup@example.com']);

    $this->postJson('/api/v1/clients', [
        'name' => 'Carlos',
        'email' => 'dup@example.com',
        'document' => '52998224725',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['email']);
});

test('rejeita documento duplicado', function () {
    Client::factory()->create(['document' => '52998224725']);

    $this->postJson('/api/v1/clients', [
        'name' => 'Bruna',
        'email' => 'bruna@example.com',
        'document' => '529.982.247-25',
    ])->assertUnprocessable()
        ->assertJsonValidationErrors(['document']);
});

test('retorna um cliente existente', function () {
    $client = Client::factory()->create();

    $this->getJson("/api/v1/clients/{$client->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $client->id)
        ->assertJsonPath('data.email', $client->email);
});

test('retorna 404 para cliente inexistente', function () {
    $this->getJson('/api/v1/clients/999999')
        ->assertNotFound();
});
