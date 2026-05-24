<?php

use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\ProxmoxServerWebController;
use App\Http\Controllers\Admin\ResourceRateController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\VirtualMachineController;
use App\Http\Controllers\Admin\VmBundleController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ServerController;
use App\Http\Controllers\Customer\WalletController as CustomerWalletController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'home')->name('home');
Route::view('/pricing', 'pricing')->name('pricing');
Route::view('/solutions', 'solutions')->name('solutions');
Route::view('/contact', 'contact')->name('contact');

$adminDomain = config('portals.admin.domain');
$customerDomain = config('portals.customer.domain');

$adminLogin = config('portals.admin.login_path');
$adminRegister = config('portals.admin.register_path');
$adminHome = config('portals.admin.home_path');
$customerLogin = config('portals.customer.login_path');
$customerRegister = config('portals.customer.register_path');
$customerHome = config('portals.customer.home_path');

Route::domain($adminDomain)->middleware('portal.host:admin')->group(function () use ($adminLogin, $adminRegister, $adminHome) {
    Route::middleware('guest:admin')->group(function () use ($adminLogin, $adminRegister) {
        Route::get($adminLogin, [AuthenticatedSessionController::class, 'create'])
            ->defaults('portal', 'admin')
            ->name('admin.login');
        Route::post($adminLogin, [AuthenticatedSessionController::class, 'store'])
            ->defaults('portal', 'admin')
            ->name('admin.login.store');

        Route::get($adminRegister, [RegisteredUserController::class, 'create'])
            ->defaults('portal', 'admin')
            ->name('admin.register');
        Route::post($adminRegister, [RegisteredUserController::class, 'store'])
            ->defaults('portal', 'admin')
            ->name('admin.register.store');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:admin')
        ->name('admin.logout');

    Route::middleware('auth:admin')->group(function () use ($adminHome) {
        Route::get($adminHome, function () {
            return view('admin.dashboard');
        })->name('admin.dashboard');

        Route::get('settings', [SettingController::class, 'edit'])->name('admin.settings.edit');
        Route::patch('settings', [SettingController::class, 'update'])->name('admin.settings.update');

        Route::post('customers/{customer}/wallet-transactions', [WalletController::class, 'storeTransaction'])
            ->name('admin.customers.wallet-transactions.store');
        Route::patch('customers/{customer}/wallet-lock', [WalletController::class, 'updateLock'])
            ->name('admin.customers.wallet-lock.update');

        Route::patch('customers/{customer}/suspend', [CustomerController::class, 'suspend'])
            ->name('admin.customers.suspend');
        Route::patch('customers/{customer}/activate', [CustomerController::class, 'activate'])
            ->name('admin.customers.activate');
        Route::resource('customers', CustomerController::class)
            ->names('admin.customers');

        Route::get('virtual-machines/proxmox-servers/{proxmoxServer}/options', [VirtualMachineController::class, 'options'])
            ->name('admin.virtual-machines.options');
        Route::post('virtual-machines/{virtualMachine}/start', [VirtualMachineController::class, 'start'])
            ->name('admin.virtual-machines.start');
        Route::post('virtual-machines/{virtualMachine}/stop', [VirtualMachineController::class, 'stop'])
            ->name('admin.virtual-machines.stop');
        Route::resource('virtual-machines', VirtualMachineController::class)
            ->parameters(['virtual-machines' => 'virtualMachine'])
            ->names('admin.virtual-machines');

        Route::prefix('billing')->name('admin.billing.')->group(function (): void {
            Route::resource('rates', ResourceRateController::class)->except(['show']);
            Route::resource('bundles', VmBundleController::class)
                ->parameters(['bundles' => 'bundle'])
                ->except(['show']);
        });

        Route::post('proxmox-servers/{proxmoxServer}/sync', [ProxmoxServerWebController::class, 'sync'])
            ->name('admin.proxmox-servers.sync');
        Route::get('proxmox-servers/{proxmoxServer}/metrics', [ProxmoxServerWebController::class, 'metrics'])
            ->name('admin.proxmox-servers.metrics');
        Route::resource('proxmox-servers', ProxmoxServerWebController::class)
            ->parameters(['proxmox-servers' => 'proxmoxServer'])
            ->names('admin.proxmox-servers');
    });
});

Route::domain($customerDomain)->middleware('portal.host:customer')->group(function () use ($customerLogin, $customerRegister, $customerHome) {
    Route::middleware('guest:customer')->group(function () use ($customerLogin, $customerRegister) {
        Route::get($customerLogin, [AuthenticatedSessionController::class, 'create'])
            ->defaults('portal', 'customer')
            ->name('customer.login');
        Route::post($customerLogin, [AuthenticatedSessionController::class, 'store'])
            ->defaults('portal', 'customer')
            ->name('customer.login.store');

        Route::get($customerRegister, [RegisteredUserController::class, 'create'])
            ->defaults('portal', 'customer')
            ->name('customer.register');
        Route::post($customerRegister, [RegisteredUserController::class, 'store'])
            ->defaults('portal', 'customer')
            ->name('customer.register.store');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:customer')
        ->name('customer.logout');

    Route::middleware('auth:customer')->group(function () use ($customerHome) {
        Route::get($customerHome, DashboardController::class)->name('dashboard');
        Route::get('servers', [ServerController::class, 'index'])->name('customer.servers.index');
        Route::get('servers/create', [ServerController::class, 'create'])->name('customer.servers.create');
        Route::get('wallet', [CustomerWalletController::class, 'show'])->name('customer.wallet.show');
        Route::post('wallet/top-ups', [PaymentController::class, 'storeTopUp'])->name('customer.wallet.topups.store');
        Route::get('wallet/payments/{payment}/gateway', [PaymentController::class, 'showGateway'])->name('customer.wallet.payments.gateway.show');
        Route::post('wallet/payments/{payment}/gateway', [PaymentController::class, 'submitGateway'])->name('customer.wallet.payments.gateway.store');
        Route::get('wallet/payments/{payment}/callback', [PaymentController::class, 'callback'])->name('customer.wallet.payments.callback');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('customer.invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('customer.invoices.show');
    });
});
