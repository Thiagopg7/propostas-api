<?php

use App\Models\Proposal;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

test('mantém a proposta em cache entre chamadas do show', function () {
    $proposal = Proposal::factory()->create();

    $this->getJson("/api/v1/propostas/{$proposal->id}")->assertOk();

    DB::enableQueryLog();

    $this->getJson("/api/v1/propostas/{$proposal->id}")->assertOk();

    $touchedProposals = collect(DB::getQueryLog())
        ->contains(fn (array $query): bool => str_contains($query['query'], 'proposals'));

    expect($touchedProposals)->toBeFalse();
});

test('invalida o cache do show ao atualizar a proposta', function () {
    $proposal = Proposal::factory()->create(['product' => 'Antigo', 'version' => 1]);

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertJsonPath('data.product', 'Antigo');

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'Novo',
    ])->assertOk();

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertJsonPath('data.product', 'Novo');
});

test('invalida o cache do show ao mudar o status', function () {
    $proposal = Proposal::factory()->create();

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertJsonPath('data.status', 'DRAFT');

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertOk();

    $this->getJson("/api/v1/propostas/{$proposal->id}")
        ->assertJsonPath('data.status', 'SUBMITTED');
});

test('invalida o cache do show ao excluir logicamente a proposta', function () {
    $proposal = Proposal::factory()->create();

    $this->getJson("/api/v1/propostas/{$proposal->id}")->assertOk();

    $this->deleteJson("/api/v1/propostas/{$proposal->id}")->assertNoContent();

    $this->getJson("/api/v1/propostas/{$proposal->id}")->assertNotFound();
});
