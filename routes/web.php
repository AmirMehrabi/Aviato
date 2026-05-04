<?php

use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('home');
});

$adminLogin = config('portals.admin.login_path');
$adminHome = config('portals.admin.home_path');
$customerLogin = config('portals.customer.login_path');
$customerRegister = config('portals.customer.register_path');
$customerHome = config('portals.customer.home_path');

Route::middleware('guest:admin')->group(function () use ($adminLogin) {
    Route::get($adminLogin, [AuthenticatedSessionController::class, 'create'])
        ->defaults('portal', 'admin')
        ->name('admin.login');
    Route::post($adminLogin, [AuthenticatedSessionController::class, 'store'])
        ->defaults('portal', 'admin')
        ->name('admin.login.store');
});

Route::middleware('guest:customer')->group(function () use ($customerLogin, $customerRegister) {
    Route::get($customerLogin, [AuthenticatedSessionController::class, 'create'])
        ->defaults('portal', 'customer')
        ->name('customer.login');
    Route::post($customerLogin, [AuthenticatedSessionController::class, 'store'])
        ->defaults('portal', 'customer')
        ->name('customer.login.store');

    Route::get($customerRegister, [RegisteredUserController::class, 'create'])->name('customer.register');
    Route::post($customerRegister, [RegisteredUserController::class, 'store'])->name('customer.register.store');
});

Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth:admin,customer')
    ->name('logout');

Route::get($adminHome, function () {
    return view('admin.dashboard');
})->middleware(['auth:admin', 'role:admin'])->name('admin.dashboard');

Route::get($customerHome, function () {
    return view('customer.dashboard');
})->middleware(['auth:customer', 'role:customer'])->name('dashboard');
