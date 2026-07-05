<?php

namespace App\Services;

use App\Enums\ProposalAuditEvent;
use App\Enums\ProposalStatus;
use App\Exceptions\ProposalStateException;
use App\Exceptions\StaleProposalVersionException;
use App\Models\Proposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    public function __construct(private readonly ProposalAuditService $audit) {}

    public static function showCacheKey(int|string $id): string
    {
        return "proposals.show.{$id}";
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Proposal
    {
        return DB::transaction(function () use ($attributes) {
            $proposal = Proposal::create($attributes);

            $this->audit->record($proposal, ProposalAuditEvent::Created, $this->snapshot($proposal));

            return $proposal;
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    public function search(array $filters, int $perPage): LengthAwarePaginator
    {
        $sortColumn = $filters['sort'] ?? 'id';
        $sortDirection = $filters['order'] ?? 'desc';

        return Proposal::query()
            ->when(isset($filters['status']), fn ($query) => $query->where('status', $filters['status']))
            ->when(isset($filters['origin']), fn ($query) => $query->where('origin', $filters['origin']))
            ->when(isset($filters['client_id']), fn ($query) => $query->where('client_id', $filters['client_id']))
            ->when(filled($filters['product'] ?? null), fn ($query) => $query->where('product', 'like', '%'.$filters['product'].'%'))
            ->when(isset($filters['min_value']), fn ($query) => $query->where('monthly_value', '>=', $filters['min_value']))
            ->when(isset($filters['max_value']), fn ($query) => $query->where('monthly_value', '<=', $filters['max_value']))
            ->orderBy($sortColumn, $sortDirection)
            ->when($sortColumn !== 'id', fn ($query) => $query->orderBy('id', 'desc'))
            ->paginate($perPage)
            ->withQueryString();
    }

    /**
     * @param  array<string, mixed>  $fields
     */
    public function update(Proposal $proposal, array $fields, int $expectedVersion): Proposal
    {
        if ($proposal->status !== ProposalStatus::Draft) {
            throw ProposalStateException::notEditable();
        }

        $updated = DB::transaction(function () use ($proposal, $fields, $expectedVersion) {
            $original = $proposal->only(array_keys($fields));

            $affected = Proposal::query()
                ->whereKey($proposal->getKey())
                ->where('version', $expectedVersion)
                ->where('status', ProposalStatus::Draft->value)
                ->update([
                    ...$fields,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                $proposal->refresh();

                if ($proposal->status !== ProposalStatus::Draft) {
                    throw ProposalStateException::notEditable();
                }

                throw new StaleProposalVersionException;
            }

            $proposal->refresh();

            $this->audit->record($proposal, ProposalAuditEvent::UpdatedFields, $this->changes($original, $fields));

            return $proposal;
        });

        Cache::forget(self::showCacheKey($updated->getKey()));

        return $updated;
    }

    public function submit(Proposal $proposal): Proposal
    {
        return $this->transition($proposal, ProposalStatus::Submitted);
    }

    public function approve(Proposal $proposal): Proposal
    {
        return $this->transition($proposal, ProposalStatus::Approved);
    }

    public function reject(Proposal $proposal): Proposal
    {
        return $this->transition($proposal, ProposalStatus::Rejected);
    }

    public function cancel(Proposal $proposal): Proposal
    {
        return $this->transition($proposal, ProposalStatus::Canceled);
    }

    public function delete(Proposal $proposal): void
    {
        DB::transaction(function () use ($proposal) {
            $proposal->delete();

            $this->audit->record($proposal, ProposalAuditEvent::DeletedLogical, [
                'status' => $proposal->status->value,
            ]);
        });

        Cache::forget(self::showCacheKey($proposal->getKey()));
    }

    private function transition(Proposal $proposal, ProposalStatus $target): Proposal
    {
        if (! $proposal->status->canTransitionTo($target)) {
            throw ProposalStateException::cannotTransition($proposal->status, $target);
        }

        $updated = DB::transaction(function () use ($proposal, $target) {
            $from = $proposal->status;

            $affected = Proposal::query()
                ->whereKey($proposal->getKey())
                ->where('status', $from->value)
                ->update([
                    'status' => $target->value,
                    'version' => DB::raw('version + 1'),
                    'updated_at' => now(),
                ]);

            if ($affected === 0) {
                throw ProposalStateException::cannotTransition($proposal->refresh()->status, $target);
            }

            $proposal->refresh();

            $this->audit->record($proposal, ProposalAuditEvent::StatusChanged, [
                'from' => $from->value,
                'to' => $target->value,
            ]);

            return $proposal;
        });

        Cache::forget(self::showCacheKey($updated->getKey()));

        return $updated;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(Proposal $proposal): array
    {
        return [
            'client_id' => $proposal->client_id,
            'product' => $proposal->product,
            'monthly_value' => $proposal->monthly_value,
            'origin' => $proposal->origin->value,
            'status' => $proposal->status->value,
        ];
    }

    /**
     * @param  array<string, mixed>  $original
     * @param  array<string, mixed>  $fields
     * @return array<string, array{from: mixed, to: mixed}>
     */
    private function changes(array $original, array $fields): array
    {
        $changes = [];

        foreach ($fields as $key => $newValue) {
            $changes[$key] = ['from' => $original[$key] ?? null, 'to' => $newValue];
        }

        return $changes;
    }
}
