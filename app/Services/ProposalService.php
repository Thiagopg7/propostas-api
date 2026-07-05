<?php

namespace App\Services;

use App\Enums\ProposalStatus;
use App\Exceptions\ProposalStateException;
use App\Exceptions\StaleProposalVersionException;
use App\Models\Proposal;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class ProposalService
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Proposal
    {
        return Proposal::create($attributes);
    }

    public function paginate(int $perPage): LengthAwarePaginator
    {
        return Proposal::query()
            ->latest('id')
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

        return $proposal->refresh();
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

    private function transition(Proposal $proposal, ProposalStatus $target): Proposal
    {
        if (! $proposal->status->canTransitionTo($target)) {
            throw ProposalStateException::cannotTransition($proposal->status, $target);
        }

        $affected = Proposal::query()
            ->whereKey($proposal->getKey())
            ->where('status', $proposal->status->value)
            ->update([
                'status' => $target->value,
                'version' => DB::raw('version + 1'),
                'updated_at' => now(),
            ]);

        if ($affected === 0) {
            throw ProposalStateException::cannotTransition($proposal->refresh()->status, $target);
        }

        return $proposal->refresh();
    }
}
