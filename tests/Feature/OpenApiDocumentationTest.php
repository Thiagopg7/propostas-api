<?php

use Dedoc\Scramble\Generator;

/**
 * @return array<string, mixed>
 */
function generatedOpenApi(): array
{
    return app(Generator::class)();
}

/**
 * @param  array<int, array<string, mixed>>  $parameters
 * @return list<string>
 */
function headerParameterNames(array $parameters): array
{
    return collect($parameters)
        ->where('in', 'header')
        ->pluck('name')
        ->all();
}

test('gera a documentação OpenAPI com os dados da API', function () {
    $document = generatedOpenApi();

    expect($document['info']['title'])->toBe('Propostas API')
        ->and($document['info']['version'])->toBe('1.0.0')
        ->and($document['paths'])->toHaveKeys([
            '/clientes',
            '/propostas',
            '/propostas/{proposal}',
            '/propostas/{proposal}/submit',
            '/propostas/{proposal}/auditoria',
        ]);
});

test('documenta a atualização de proposta como PATCH', function () {
    $operations = generatedOpenApi()['paths']['/propostas/{proposal}'] ?? [];

    expect($operations)->toHaveKey('patch')
        ->and($operations)->not->toHaveKey('put');
});

test('documenta os parâmetros de busca em GET propostas', function () {
    $parameters = generatedOpenApi()['paths']['/propostas']['get']['parameters'] ?? [];

    $names = collect($parameters)->pluck('name');

    expect($names)->toContain('status', 'origin', 'client_id', 'product', 'min_value', 'max_value', 'sort', 'order');
});

test('documenta o cabeçalho Idempotency-Key nas rotas idempotentes', function () {
    $paths = generatedOpenApi()['paths'];

    expect(headerParameterNames($paths['/propostas']['post']['parameters'] ?? []))
        ->toContain('Idempotency-Key')
        ->and(headerParameterNames($paths['/propostas/{proposal}/submit']['post']['parameters'] ?? []))
        ->toContain('Idempotency-Key');
});

test('não exige Idempotency-Key em rotas não idempotentes', function () {
    $parameters = generatedOpenApi()['paths']['/propostas/{proposal}/approve']['post']['parameters'] ?? [];

    expect(headerParameterNames($parameters))->not->toContain('Idempotency-Key');
});
