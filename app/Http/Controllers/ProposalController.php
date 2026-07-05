<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

class ProposalController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        $proposals = Proposal::query()
            ->latest('id')
            ->paginate($perPage)
            ->withQueryString();

        return ProposalResource::collection($proposals);
    }

    public function store(StoreProposalRequest $request): JsonResponse
    {
        $proposal = Proposal::create($request->validated());

        return ProposalResource::make($proposal)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Proposal $proposal): ProposalResource
    {
        return ProposalResource::make($proposal);
    }
}
