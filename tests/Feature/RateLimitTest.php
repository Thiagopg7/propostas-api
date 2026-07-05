<?php

test('inclui cabeçalhos de rate limit nas respostas', function () {
    $this->getJson('/api/v1/propostas')
        ->assertOk()
        ->assertHeader('X-RateLimit-Limit', 60)
        ->assertHeader('X-RateLimit-Remaining', 59);
});

test('bloqueia requisições acima do limite com 429', function () {
    for ($request = 0; $request < 60; $request++) {
        $this->getJson('/api/v1/propostas')->assertOk();
    }

    $this->getJson('/api/v1/propostas')
        ->assertStatus(429)
        ->assertJsonPath('message', 'Muitas requisições. Aguarde um instante e tente novamente.');
});
