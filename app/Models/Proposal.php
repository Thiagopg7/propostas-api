<?php

namespace App\Models;

use App\Enums\ProposalOrigin;
use App\Enums\ProposalStatus;
use Database\Factories\ProposalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Proposal extends Model
{
    /** @use HasFactory<ProposalFactory> */
    use HasFactory;

    use SoftDeletes;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'client_id',
        'product',
        'monthly_value',
        'status',
        'origin',
        'version',
    ];

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'status' => ProposalStatus::Draft->value,
        'version' => 1,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ProposalStatus::class,
            'origin' => ProposalOrigin::class,
            'monthly_value' => 'decimal:2',
            'version' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Client, $this>
     */
    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    /**
     * @return HasMany<ProposalAudit, $this>
     */
    public function audits(): HasMany
    {
        return $this->hasMany(ProposalAudit::class);
    }
}
