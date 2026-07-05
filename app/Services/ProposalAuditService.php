<?php

namespace App\Services;

use App\Enums\ProposalAuditEvent;
use App\Models\Proposal;
use App\Models\ProposalAudit;
use Illuminate\Http\Request;

class ProposalAuditService
{
    public function __construct(private readonly Request $request) {}

    /**
     * @param  array<string, mixed>  $payload
     */
    public function record(Proposal $proposal, ProposalAuditEvent $event, array $payload = []): ProposalAudit
    {
        return $proposal->audits()->create([
            'actor' => $this->actor(),
            'event' => $event,
            'payload' => $payload,
        ]);
    }

    private function actor(): string
    {
        $actor = trim((string) $this->request->header('X-Actor'));

        return $actor === '' ? 'system' : $actor;
    }
}
