<?php

test('retorna erro 404 em JSON mesmo sem o header Accept', function () {
    $this->get('/api/v1/clientes/999999')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

test('retorna mensagem amigável ao não encontrar cliente', function () {
    $this->getJson('/api/v1/clientes/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Cliente não encontrado.']);
});

test('retorna mensagem amigável ao não encontrar proposta', function () {
    $this->getJson('/api/v1/propostas/999999')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Proposta não encontrada.']);
});

test('retorna mensagem amigável para rota inexistente', function () {
    $this->getJson('/api/v1/rota-inexistente')
        ->assertNotFound()
        ->assertExactJson(['message' => 'Rota não encontrada.']);
});

test('retorna erros de validação em JSON mesmo sem o header Accept', function () {
    $this->post('/api/v1/clientes', [])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonValidationErrors(['name', 'email', 'document']);
});
