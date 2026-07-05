<?php

namespace App\Http\Controllers;

use App\Http\Resources\ProposalAuditResource;
use App\Models\Proposal;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ProposalAuditController extends Controller
{
    private const DEFAULT_PER_PAGE = 15;

    private const MAX_PER_PAGE = 100;

    public function index(Request $request, Proposal $proposal): AnonymousResourceCollection
    {
        $perPage = max(1, min($request->integer('per_page', self::DEFAULT_PER_PAGE), self::MAX_PER_PAGE));

        return ProposalAuditResource::collection(
            $proposal->audits()->latest('id')->paginate($perPage)
        );
    }
}
