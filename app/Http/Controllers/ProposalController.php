<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use App\Services\ProposalService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Symfony\Component\HttpFoundation\Response;

class ProposalController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function __construct(private readonly ProposalService $proposals) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return ProposalResource::collection($this->proposals->paginate($perPage));
    }

    public function store(StoreProposalRequest $request): JsonResponse
    {
        $proposal = $this->proposals->create($request->validated());

        return ProposalResource::make($proposal)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Proposal $proposal): ProposalResource
    {
        return ProposalResource::make($proposal);
    }

    public function update(UpdateProposalRequest $request, Proposal $proposal): ProposalResource
    {
        $validated = $request->validated();

        $proposal = $this->proposals->update(
            $proposal,
            Arr::except($validated, ['version']),
            (int) $validated['version'],
        );

        return ProposalResource::make($proposal);
    }

    public function submit(Proposal $proposal): ProposalResource
    {
        return ProposalResource::make($this->proposals->submit($proposal));
    }

    public function approve(Proposal $proposal): ProposalResource
    {
        return ProposalResource::make($this->proposals->approve($proposal));
    }

    public function reject(Proposal $proposal): ProposalResource
    {
        return ProposalResource::make($this->proposals->reject($proposal));
    }

    public function cancel(Proposal $proposal): ProposalResource
    {
        return ProposalResource::make($this->proposals->cancel($proposal));
    }
}
