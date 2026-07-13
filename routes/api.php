<?php

use App\Http\Controllers\Admin\ProxmoxServerController;
use App\Http\Controllers\Api\WalletController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.audit', 'auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::prefix('projects/{project}/wallet')->middleware('abilities:wallet:read')->group(function (): void {
        Route::get('/', [WalletController::class, 'show'])->name('api.v1.wallet.show');
        Route::get('transactions', [WalletController::class, 'transactions'])->name('api.v1.wallet.transactions');
        Route::get('transactions/{transaction}', [WalletController::class, 'transaction'])->name('api.v1.wallet.transaction');
    });
});

Route::domain(config('portals.admin.domain'))
    ->middleware(['web', 'portal.host:admin', 'auth:admin'])
    ->name('api.admin.')
    ->group(function () {
        Route::apiResource('proxmox-servers', ProxmoxServerController::class)
            ->parameters(['proxmox-servers' => 'proxmoxServer']);
    });
