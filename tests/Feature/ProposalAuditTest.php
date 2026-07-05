<?php

use App\Enums\ProposalAuditEvent;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalAudit;
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

test('lista a auditoria da mais recente para a mais antiga', function () {
    $proposal = Proposal::factory()->create();
    $older = ProposalAudit::factory()->for($proposal)->create(['event' => ProposalAuditEvent::Created]);
    $newer = ProposalAudit::factory()->for($proposal)->create(['event' => ProposalAuditEvent::StatusChanged]);

    $this->getJson("/api/v1/propostas/{$proposal->id}/auditoria")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.id', $newer->id)
        ->assertJsonPath('data.1.id', $older->id);
});

test('retorna a auditoria com a estrutura esperada', function () {
    $proposal = Proposal::factory()->create();
    ProposalAudit::factory()->for($proposal)->create([
        'event' => ProposalAuditEvent::Created,
        'actor' => 'user:9',
        'payload' => ['status' => 'DRAFT'],
    ]);

    $this->getJson("/api/v1/propostas/{$proposal->id}/auditoria")
        ->assertOk()
        ->assertJsonStructure(['data' => [['id', 'proposal_id', 'actor', 'event', 'payload', 'created_at']]])
        ->assertJsonPath('data.0.actor', 'user:9')
        ->assertJsonPath('data.0.event', 'CREATED')
        ->assertJsonPath('data.0.payload.status', 'DRAFT');
});

test('retorna lista vazia quando a proposta não tem auditoria', function () {
    $proposal = Proposal::factory()->create();

    $this->getJson("/api/v1/propostas/{$proposal->id}/auditoria")
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

test('a auditoria acumula os eventos do ciclo de vida da proposta', function () {
    $created = $this->postJson('/api/v1/propostas', createProposalPayload(), [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertCreated();

    $id = $created->json('data.id');

    $this->postJson("/api/v1/propostas/{$id}/submit", [], [
        'Idempotency-Key' => (string) Str::uuid(),
    ])->assertOk();

    $this->getJson("/api/v1/propostas/{$id}/auditoria")
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.event', 'STATUS_CHANGED')
        ->assertJsonPath('data.1.event', 'CREATED');
});

test('retorna 404 ao consultar auditoria de proposta inexistente', function () {
    $this->getJson('/api/v1/propostas/999999/auditoria')->assertNotFound();
});
