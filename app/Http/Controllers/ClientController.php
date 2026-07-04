<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreClientRequest;
use App\Http\Resources\ClientResource;
use App\Models\Client;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\Response;

class ClientController extends Controller
{
    public function store(StoreClientRequest $request): JsonResponse
    {
        $client = Client::create($request->validated());

        return ClientResource::make($client)
            ->response()
            ->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(Client $client): ClientResource
    {
        return ClientResource::make($client);
    }
}
