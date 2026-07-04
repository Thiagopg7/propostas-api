<?php

use App\Rules\Document;

function documentPasses(mixed $value): bool
{
    $failed = false;

    (new Document)->validate('document', $value, function () use (&$failed) {
        $failed = true;
    });

    return ! $failed;
}

test('aceita CPF válido com e sem máscara', function () {
    expect(documentPasses('529.982.247-25'))->toBeTrue()
        ->and(documentPasses('52998224725'))->toBeTrue();
});

test('aceita CNPJ válido com e sem máscara', function () {
    expect(documentPasses('11.222.333/0001-81'))->toBeTrue()
        ->and(documentPasses('11222333000181'))->toBeTrue();
});

test('rejeita documento com dígito verificador inválido', function (string $document) {
    expect(documentPasses($document))->toBeFalse();
})->with([
    'cpf inválido' => '52998224726',
    'cnpj inválido' => '11222333000180',
]);

test('rejeita dígitos repetidos', function (string $document) {
    expect(documentPasses($document))->toBeFalse();
})->with([
    'cpf zerado' => '00000000000',
    'cpf repetido' => '11111111111',
    'cnpj repetido' => '11111111111111',
]);

test('rejeita tamanho inválido ou vazio', function (string $document) {
    expect(documentPasses($document))->toBeFalse();
})->with([
    'curto' => '123',
    'entre cpf e cnpj' => '123456789012',
    'vazio' => '',
]);

test('rejeita valor não escalar sem gerar erro', function () {
    expect(documentPasses(['52998224725']))->toBeFalse();
});
