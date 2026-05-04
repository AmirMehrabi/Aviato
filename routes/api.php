<?php

use App\Http\Controllers\Admin\ProxmoxServerController;
use Illuminate\Support\Facades\Route;

Route::domain(config('portals.admin.domain'))
    ->middleware(['web', 'portal.host:admin', 'auth:admin'])
    ->name('api.admin.')
    ->group(function () {
        Route::apiResource('proxmox-servers', ProxmoxServerController::class)
            ->parameters(['proxmox-servers' => 'proxmoxServer']);
    });
