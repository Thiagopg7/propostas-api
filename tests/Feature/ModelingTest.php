<?php

use App\Enums\ProposalAuditEvent;
use App\Enums\ProposalOrigin;
use App\Enums\ProposalStatus;
use App\Models\Client;
use App\Models\Proposal;
use App\Models\ProposalAudit;
use Illuminate\Support\Facades\Schema;

it('relates a client to its proposals', function () {
    $client = Client::factory()->has(Proposal::factory()->count(2))->create();

    expect($client->proposals)->toHaveCount(2)
        ->and($client->proposals->first()->client->is($client))->toBeTrue();
});

it('casts proposal attributes to the right types', function () {
    $proposal = Proposal::factory()->create([
        'monthly_value' => 1234.5,
        'origin' => ProposalOrigin::Site,
    ]);

    expect($proposal->status)->toBeInstanceOf(ProposalStatus::class)
        ->and($proposal->origin)->toBe(ProposalOrigin::Site)
        ->and($proposal->monthly_value)->toBe('1234.50')
        ->and($proposal->version)->toBeInt();
});

it('applies default status and version to a new proposal', function () {
    $proposal = new Proposal;

    expect($proposal->status)->toBe(ProposalStatus::Draft)
        ->and($proposal->version)->toBe(1);
});

it('soft deletes a proposal', function () {
    $proposal = Proposal::factory()->create();

    $proposal->delete();

    expect($proposal->trashed())->toBeTrue();
    $this->assertSoftDeleted($proposal);
});

it('stores audits with json payload and without updated_at', function () {
    $audit = ProposalAudit::factory()->create([
        'event' => ProposalAuditEvent::Created,
        'payload' => ['field' => 'status', 'from' => 'DRAFT', 'to' => 'SUBMITTED'],
    ]);

    expect($audit->event)->toBe(ProposalAuditEvent::Created)
        ->and($audit->payload)->toBe(['field' => 'status', 'from' => 'DRAFT', 'to' => 'SUBMITTED'])
        ->and($audit->proposal)->toBeInstanceOf(Proposal::class)
        ->and(Schema::hasColumn('proposal_audits', 'updated_at'))->toBeFalse();
});

it('marks only approved, rejected and canceled as final states', function (ProposalStatus $status, bool $expected) {
    expect($status->isFinal())->toBe($expected);
})->with([
    'draft' => [ProposalStatus::Draft, false],
    'submitted' => [ProposalStatus::Submitted, false],
    'approved' => [ProposalStatus::Approved, true],
    'rejected' => [ProposalStatus::Rejected, true],
    'canceled' => [ProposalStatus::Canceled, true],
]);

it('generates unique clients through the factory', function () {
    $clients = Client::factory()->count(5)->create();

    expect($clients->pluck('email')->unique())->toHaveCount(5)
        ->and($clients->pluck('document')->unique())->toHaveCount(5);
});
