<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProposalController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::prefix('v1')->group(function () {
    Route::apiResource('clientes', ClientController::class)
        ->only(['store', 'show'])
        ->parameters(['clientes' => 'client']);

    Route::apiResource('propostas', ProposalController::class)
        ->only(['store', 'show'])
        ->parameters(['propostas' => 'proposal'])
        ->middlewareFor('store', 'idempotency');
});
