<?php

use App\Models\Client;
use App\Models\Proposal;
use Illuminate\Support\Str;

function createProposalPayload(array $overrides = []): array
{
    return array_merge([
        'client_id' => Client::factory()->create()->id,
        'product' => 'Plano Ouro',
        'monthly_value' => 199.90,
        'origin' => 'APP',
    ], $overrides);
}

test('registra auditoria CREATED ao criar proposta com actor padrão system', function () {
    $response = $this->postJson('/api/v1/propostas', createProposalPayload(), [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $this->assertDatabaseHas('proposal_audits', [
        'proposal_id' => $response->json('data.id'),
        'event' => 'CREATED',
        'actor' => 'system',
    ]);
});

test('registra o actor a partir do header X-Actor', function () {
    $response = $this->postJson('/api/v1/propostas', createProposalPayload(), [
        'Idempotency-Key' => (string) Str::uuid(),
        'X-Actor' => 'user:42',
    ])->assertCreated();

    $this->assertDatabaseHas('proposal_audits', [
        'proposal_id' => $response->json('data.id'),
        'event' => 'CREATED',
        'actor' => 'user:42',
    ]);
});

test('registra auditoria UPDATED_FIELDS com os valores anterior e novo', function () {
    $proposal = Proposal::factory()->create(['product' => 'Antigo', 'version' => 1]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 1,
        'product' => 'Novo',
    ])->assertOk();

    $audit = $proposal->audits()->where('event', 'UPDATED_FIELDS')->first();

    expect($audit)->not->toBeNull();
    expect($audit->payload['product'])->toBe(['from' => 'Antigo', 'to' => 'Novo']);
});

test('registra auditoria STATUS_CHANGED ao submeter', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertOk();

    $audit = $proposal->audits()->where('event', 'STATUS_CHANGED')->first();

    expect($audit)->not->toBeNull();
    expect($audit->payload)->toBe(['from' => 'DRAFT', 'to' => 'SUBMITTED']);
});

test('registra auditoria STATUS_CHANGED ao aprovar', function () {
    $proposal = Proposal::factory()->submitted()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/approve")->assertOk();

    $audit = $proposal->audits()->where('event', 'STATUS_CHANGED')->first();

    expect($audit->payload)->toBe(['from' => 'SUBMITTED', 'to' => 'APPROVED']);
});

test('submeter de forma idempotente registra apenas uma auditoria', function () {
    $proposal = Proposal::factory()->create(['version' => 1]);
    $headers = ['Idempotency-Key' => 'chave-audit'];

    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], $headers)->assertOk();
    $this->postJson("/api/v1/propostas/{$proposal->id}/submit", [], $headers)->assertOk();

    expect($proposal->audits()->where('event', 'STATUS_CHANGED')->count())->toBe(1);
});

test('não registra auditoria em transição inválida', function () {
    $proposal = Proposal::factory()->create();

    $this->postJson("/api/v1/propostas/{$proposal->id}/approve")->assertUnprocessable();

    expect($proposal->audits()->count())->toBe(0);
});

test('não registra auditoria quando a atualização falha por conflito de versão', function () {
    $proposal = Proposal::factory()->create(['version' => 3]);

    $this->patchJson("/api/v1/propostas/{$proposal->id}", [
        'version' => 2,
        'product' => 'X',
    ])->assertStatus(409);

    expect($proposal->audits()->where('event', 'UPDATED_FIELDS')->count())->toBe(0);
});

test('exclui logicamente uma proposta e registra auditoria DELETED_LOGICAL', function () {
    $proposal = Proposal::factory()->create();

    $this->deleteJson("/api/v1/propostas/{$proposal->id}")
        ->assertNoContent();

    $this->assertSoftDeleted('proposals', ['id' => $proposal->id]);

    $this->assertDatabaseHas('proposal_audits', [
        'proposal_id' => $proposal->id,
        'event' => 'DELETED_LOGICAL',
        'actor' => 'system',
    ]);
});

test('registra o actor da exclusão a partir do header X-Actor', function () {
    $proposal = Proposal::factory()->create();

    $this->deleteJson("/api/v1/propostas/{$proposal->id}", [], ['X-Actor' => 'user:7'])
        ->assertNoContent();

    $this->assertDatabaseHas('proposal_audits', [
        'proposal_id' => $proposal->id,
        'event' => 'DELETED_LOGICAL',
        'actor' => 'user:7',
    ]);
});

test('não consulta proposta após exclusão lógica', function () {
    $proposal = Proposal::factory()->create();

    $this->deleteJson("/api/v1/propostas/{$proposal->id}")->assertNoContent();

    $this->getJson("/api/v1/propostas/{$proposal->id}")->assertNotFound();
});

test('retorna 404 ao excluir proposta inexistente', function () {
    $this->deleteJson('/api/v1/propostas/999999')->assertNotFound();
});

test('retorna 404 ao excluir proposta já excluída logicamente', function () {
    $proposal = Proposal::factory()->create();
    $proposal->delete();

    $this->deleteJson("/api/v1/propostas/{$proposal->id}")->assertNotFound();
});
