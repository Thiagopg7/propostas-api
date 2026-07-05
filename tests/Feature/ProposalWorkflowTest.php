<?php

use App\Models\Proposal;
use Illuminate\Support\Str;

function submitHeader(?string $key = null): array
{
    return ['Idempotency-Key' => $key ?? (string) Str::uuid()];
}

test('submete um rascunho e incrementa a versão', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], submitHeader())
        ->assertOk()
        ->assertJsonPath('data.status', 'SUBMITTED')
        ->assertJsonPath('data.version', 2);

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'SUBMITTED',
        'version' => 2,
    ]);
});

test('exige Idempotency-Key para submeter', function () {
    $proposal = Proposal::factory()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit")
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['idempotency_key']);

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'DRAFT',
    ]);
});

test('submeter é idempotente com a mesma Idempotency-Key', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);
    $headers = submitHeader('chave-submit');

    $first = $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], $headers)->assertOk();
    $second = $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], $headers)->assertOk();

    expect($second->json('data.version'))->toBe($first->json('data.version'));

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'SUBMITTED',
        'version' => 2,
    ]);
});

test('não submete proposta que já saiu do rascunho', function () {
    $proposal = Proposal::factory()->submitted()->create(['version' => 1]);

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], submitHeader())
        ->assertUnprocessable();

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'SUBMITTED',
        'version' => 1,
    ]);
});

test('aprova uma proposta submetida e incrementa a versão', function () {
    $proposal = Proposal::factory()->submitted()->create(['version' => 2]);

    $this->postJson("/api/v1/propostas/{$proposal->id}/approve")
        ->assertOk()
        ->assertJsonPath('data.status', 'APPROVED')
        ->assertJsonPath('data.version', 3);
});

test('rejeita uma proposta submetida', function () {
    $proposal = Proposal::factory()->submitted()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/reject")
        ->assertOk()
        ->assertJsonPath('data.status', 'REJECTED');
});

test('não aprova uma proposta em rascunho', function () {
    $proposal = Proposal::factory()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/approve")
        ->assertUnprocessable();

    $this->assertDatabaseHas('proposals', [
        'id' => $proposal->id,
        'status' => 'DRAFT',
    ]);
});

test('cancela uma proposta em rascunho', function () {
    $proposal = Proposal::factory()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELED');
});

test('cancela uma proposta submetida', function () {
    $proposal = Proposal::factory()->submitted()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/cancel")
        ->assertOk()
        ->assertJsonPath('data.status', 'CANCELED');
});

test('não transiciona a partir de um estado final', function (string $state, string $action) {
    $proposal = Proposal::factory()->{$state}()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/{$action}")
        ->assertUnprocessable();

    expect($proposal->fresh()->status->value)->toBe(strtoupper($state));
})->with(function () {
    foreach (['approved', 'rejected', 'canceled'] as $state) {
        foreach (['approve', 'reject', 'cancel'] as $action) {
            yield "{$state} não aceita {$action}" => [$state, $action];
        }
    }
});

test('não submete proposta em estado final', function (string $state) {
    $proposal = Proposal::factory()->{$state}()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], submitHeader())
        ->assertUnprocessable();
})->with(['approved', 'rejected', 'canceled']);

test('retorna 404 ao transicionar proposta inexistente', function () {
    $this->postJson('/api/v1/propostas/999999/approve')
        ->assertNotFound();
});

test('retorna 404 ao transicionar proposta excluída logicamente', function () {
    $proposal = Proposal::factory()->submitted()->create();
    $proposal->delete();

    $this->postJson("/api/v1/propostas/{$proposal->id}/approve")
        ->assertNotFound();
});
