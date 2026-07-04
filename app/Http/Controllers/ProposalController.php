<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ProposalController extends Controller
{
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
