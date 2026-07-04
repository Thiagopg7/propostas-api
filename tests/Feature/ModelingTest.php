<?php

use App\Enums\ProposalAuditEvent;
use App\Enums\ProposalOrigin;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalAudit;
use Illuminate\Support\Facades\Schema;

test('relaciona um cliente às suas propostas', function () {
    $client = Client::factory()->has(Proposal::factory()->count(2))->create();

    expect($client->proposals)->toHaveCount(2)
        ->and($client->proposals->first()->client->is($client))->toBeTrue();
});

test('faz cast dos atributos da proposta para os tipos corretos', function () {
    $proposal = Proposal::factory()->create([
        'monthly_value' => 1234.5,
        'origin' => ProposalOrigin::Site,
    ]);

    expect($proposal->status)->toBeInstanceOf(ProposalStatus::class)
        ->and($proposal->origin)->toBe(ProposalOrigin::Site)
        ->and($proposal->monthly_value)->toBe('1234.50')
        ->and($proposal->version)->toBeInt();
});

test('aplica status e versão padrão a uma nova proposta', function () {
    $proposal = new Proposal;

    expect($proposal->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->version)->toBe(1);
});

test('faz exclusão lógica de uma proposta', function () {
    $proposal = Proposal::factory()->create();

    $proposal->delete();

    expect($proposal->trashed())->toBeTrue();
    $this->assertSoftDeleted($proposal);
});

test('armazena auditorias com payload json e sem updated_at', function () {
    $audit = ProposalAudit::factory()->create([
        'event' => ProposalAuditEvent::Created,
        'payload' => ['field' => 'status', 'from' => 'DRAFT', 'to' => 'SUBMITTED'],
    ]);

    expect($audit->event)->toBe(ProposalAuditEvent::Created)
        ->and($audit->payload)->toBe(['field' => 'status', 'from' => 'DRAFT', 'to' => 'SUBMITTED'])
        ->and($audit->proposal)->toBeInstanceOf(Proposal::class)
        ->and(Schema::hasColumn('proposal_audits', 'updated_at'))->toBeFalse();
});

test('marca apenas approved, rejected e canceled como estados finais', function (ProposalStatus $status, bool $expected) {
    expect($status->isFinal())->toBe($expected);
})->with([
    'draft' => [ProposalStatus::Draft, false],
    'submitted' => [ProposalStatus::Submitted, false],
    'approved' => [ProposalStatus::Approved, true],
    'rejected' => [ProposalStatus::Rejected, true],
    'canceled' => [ProposalStatus::Canceled, true],
]);

test('gera clientes únicos pela factory', function () {
    $clients = Client::factory()->count(5)->create();

    expect($clients->pluck('email')->unique())->toHaveCount(5)
        ->and($clients->pluck('document')->unique())->toHaveCount(5);
});
