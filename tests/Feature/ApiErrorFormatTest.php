<?php

test('retorna erro 404 em JSON mesmo sem o header Accept', function () {
    $this->get('/api/v1/clientes/999999')
        ->assertNotFound()
        ->assertHeader('Content-Type', 'application/json');
});

test('retorna erros de validação em JSON mesmo sem o header Accept', function () {
    $this->post('/api/v1/clientes', [])
        ->assertUnprocessable()
        ->assertHeader('Content-Type', 'application/json')
        ->assertJsonValidationErrors(['name', 'email', 'document']);
});
