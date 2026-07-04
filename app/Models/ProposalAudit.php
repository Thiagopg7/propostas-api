<?php

namespace App\Models;

use App\Enums\ProposalAuditEvent;
use Database\Factories\ProposalAuditFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProposalAudit extends Model
{
    /** @use HasFactory<ProposalAuditFactory> */
    use HasFactory;

    const UPDATED_AT = null;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'proposal_id',
        'actor',
        'event',
        'payload',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => ProposalAuditEvent::class,
            'payload' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Proposal, $this>
     */
    public function proposal(): BelongsTo
    {
        return $this->belongsTo(Proposal::class);
    }
}
