<?php

namespace App\Http\Controllers;

use App\Enums\ProposalStatus;
use App\Http\Requests\StoreProposalRequest;
use App\Http\Requests\UpdateProposalRequest;
use App\Http\Resources\ProposalResource;
use App\Models\Proposal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
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

    public function update(UpdateProposalRequest $request, Proposal $proposal): JsonResponse|ProposalResource
    {
        if ($proposal->status !== ProposalStatus::Draft) {
            return $this->notEditable();
        }

        $validated = $request->validated();
        $expectedVersion = (int) $validated['version'];
        $fields = Arr::except($validated, ['version']);

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

            return $proposal->status === ProposalStatus::Draft
                ? $this->versionConflict()
                : $this->notEditable();
        }

        return ProposalResource::make($proposal->refresh());
    }

    private function notEditable(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'Apenas propostas em rascunho podem ser editadas.',
        ], Response::HTTP_UNPROCESSABLE_ENTITY);
    }

    private function versionConflict(): JsonResponse
    {
        return new JsonResponse([
            'message' => 'A proposta foi modificada por outra requisição. Recarregue e tente novamente.',
        ], Response::HTTP_CONFLICT);
    }
}
