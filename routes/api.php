<?php

use App\Http\Controllers\ClientController;
use App\Http\Controllers\ProposalAuditController;
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

    Route::post('propostas/{proposal}/submit', [ProposalController::class, 'submit'])
        ->middleware('idempotency')
        ->name('propostas.submit');
    Route::post('propostas/{proposal}/approve', [ProposalController::class, 'approve'])
        ->name('propostas.approve');
    Route::post('propostas/{proposal}/reject', [ProposalController::class, 'reject'])
        ->name('propostas.reject');
    Route::post('propostas/{proposal}/cancel', [ProposalController::class, 'cancel'])
        ->name('propostas.cancel');
    Route::get('propostas/{proposal}/auditoria', [ProposalAuditController::class, 'index'])
        ->name('propostas.auditoria');

    Route::apiResource('propostas', ProposalController::class)
        ->only(['index', 'store', 'show', 'update', 'destroy'])
        ->parameters(['propostas' => 'proposal'])
        ->middlewareFor('store', 'idempotency');
});
