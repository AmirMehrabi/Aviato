<?php

use App\Http\Controllers\Admin\ProxmoxServerController;
use App\Http\Controllers\Api\WalletController;
use App\Http\Controllers\Api\VirtualMachineController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware(['api.audit', 'auth:sanctum', 'throttle:60,1'])->group(function (): void {
    Route::prefix('projects/{project}/wallet')->middleware('abilities:wallet:read')->group(function (): void {
        Route::get('/', [WalletController::class, 'show'])->name('api.v1.wallet.show');
        Route::get('transactions', [WalletController::class, 'transactions'])->name('api.v1.wallet.transactions');
        Route::get('transactions/{transaction}', [WalletController::class, 'transaction'])->name('api.v1.wallet.transaction');
    });

    Route::prefix('projects/{project}/virtual-machines')->group(function (): void {
        Route::get('options', [VirtualMachineController::class, 'options'])
            ->middleware('abilities:vm:read')
            ->name('api.v1.virtual-machines.options');
        Route::get('/', [VirtualMachineController::class, 'index'])
            ->middleware('abilities:vm:read')
            ->name('api.v1.virtual-machines.index');
        Route::get('{virtual_machine}', [VirtualMachineController::class, 'show'])
            ->middleware('abilities:vm:read')
            ->name('api.v1.virtual-machines.show');
        Route::post('/', [VirtualMachineController::class, 'store'])
            ->middleware('abilities:vm:create')
            ->name('api.v1.virtual-machines.store');
        Route::delete('{virtual_machine}', [VirtualMachineController::class, 'destroy'])
            ->middleware('abilities:vm:delete')
            ->name('api.v1.virtual-machines.destroy');
    });
});

Route::domain(config('portals.admin.domain'))
    ->middleware(['web', 'portal.host:admin', 'auth:admin'])
    ->name('api.admin.')
    ->group(function () {
        Route::apiResource('proxmox-servers', ProxmoxServerController::class)
            ->parameters(['proxmox-servers' => 'proxmoxServer']);
    });
