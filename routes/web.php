<?php

use App\Http\Controllers\Admin\CloudImageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\IpPoolController;
use App\Http\Controllers\Admin\ProxmoxServerWebController;
use App\Http\Controllers\Admin\ResourceRateController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\VirtualMachineController;
use App\Http\Controllers\Admin\VmBundleController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CustomerEmailVerificationController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\Customer\BackupController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\MonitoringController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ServerConsoleController;
use App\Http\Controllers\Customer\ServerController;
use App\Http\Controllers\Customer\VmUpgradeController;
use App\Http\Controllers\Customer\WalletController as CustomerWalletController;
use App\Models\VmBundle;
use App\Services\WalletService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

$adminDomain = config('portals.admin.domain');
$customerDomain = config('portals.customer.domain');

$adminLogin = config('portals.admin.login_path');
$adminHome = config('portals.admin.home_path');
$customerLogin = config('portals.customer.login_path');
$customerRegister = config('portals.customer.register_path');
$customerHome = config('portals.customer.home_path');

Route::domain($adminDomain)->middleware('portal.host:admin')->group(function () use ($adminLogin, $adminHome) {
    Route::get('/', fn () => redirect('/'.trim($adminHome, '/')))
        ->middleware('auth:admin')
        ->name('admin.home');

    Route::middleware('guest:admin')->group(function () use ($adminLogin) {
        Route::get($adminLogin, [AuthenticatedSessionController::class, 'create'])
            ->defaults('portal', 'admin')
            ->name('admin.login');
        Route::post($adminLogin, [AuthenticatedSessionController::class, 'store'])
            ->defaults('portal', 'admin')
            ->name('admin.login.store');
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
        Route::post('virtual-machines/{virtualMachine}/retry-provisioning', [VirtualMachineController::class, 'retryProvisioning'])
            ->name('admin.virtual-machines.retry-provisioning');
        Route::resource('virtual-machines', VirtualMachineController::class)
            ->parameters(['virtual-machines' => 'virtualMachine'])
            ->names('admin.virtual-machines');
        Route::resource('cloud-images', CloudImageController::class)
            ->parameters(['cloud-images' => 'cloudImage'])
            ->except(['show'])
            ->names('admin.cloud-images');
        Route::resource('ip-pools', IpPoolController::class)
            ->parameters(['ip-pools' => 'ipPool'])
            ->except(['show'])
            ->names('admin.ip-pools');

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
        Route::delete('proxmox-servers/{proxmoxServer}/stale-virtual-machines', [ProxmoxServerWebController::class, 'destroyStaleVirtualMachines'])
            ->name('admin.proxmox-servers.stale-virtual-machines.destroy-bulk');
        Route::delete('proxmox-servers/{proxmoxServer}/stale-virtual-machines/{virtualMachine}', [ProxmoxServerWebController::class, 'destroyStaleVirtualMachine'])
            ->name('admin.proxmox-servers.stale-virtual-machines.destroy');
        Route::resource('proxmox-servers', ProxmoxServerWebController::class)
            ->parameters(['proxmox-servers' => 'proxmoxServer'])
            ->names('admin.proxmox-servers');
    });
});

Route::domain($customerDomain)->middleware('portal.host:customer')->group(function () use ($customerLogin, $customerRegister, $customerHome) {
    Route::get('/', fn () => redirect('/'.trim($customerHome, '/')))
        ->middleware('auth:customer')
        ->name('customer.home');

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

        Route::get('email/verify', [CustomerEmailVerificationController::class, 'create'])
            ->name('customer.verification.notice');
        Route::post('email/verify', [CustomerEmailVerificationController::class, 'store'])
            ->name('customer.verification.verify');
        Route::post('email/verify/resend', [CustomerEmailVerificationController::class, 'resend'])
            ->name('customer.verification.resend');
    });

    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
        ->middleware('auth:customer')
        ->name('customer.logout');

    Route::middleware('auth:customer')->group(function () use ($customerHome) {
        Route::get($customerHome, DashboardController::class)->name('dashboard');
        Route::get('servers', [ServerController::class, 'index'])->name('customer.servers.index');
        Route::get('servers/create', [ServerController::class, 'create'])->name('customer.servers.create');
        Route::post('servers', [ServerController::class, 'store'])->name('customer.servers.store');
        Route::get('servers/statuses', [ServerController::class, 'statuses'])->name('customer.servers.statuses');
        Route::get('servers/{virtualMachine}/console', [ServerConsoleController::class, 'show'])->name('customer.servers.console.show');
        Route::post('servers/{virtualMachine}/console/session', [ServerConsoleController::class, 'session'])->name('customer.servers.console.session');
        Route::get('servers/{virtualMachine}', [ServerController::class, 'show'])->name('customer.servers.show');
        Route::post('servers/{virtualMachine}/upgrades/bundle', [VmUpgradeController::class, 'storeBundle'])->name('customer.servers.upgrades.bundle.store');
        Route::post('servers/{virtualMachine}/upgrades/extra-disk', [VmUpgradeController::class, 'storeExtraDisk'])->name('customer.servers.upgrades.extra-disk.store');
        Route::delete('servers/{virtualMachine}', [ServerController::class, 'destroy'])->name('customer.servers.destroy');
        Route::get('backups', [BackupController::class, 'index'])->name('customer.backups.index');
        Route::post('backups/servers/{virtualMachine}', [BackupController::class, 'storeManual'])->name('customer.backups.manual.store');
        Route::patch('backups/servers/{virtualMachine}/policy', [BackupController::class, 'updatePolicy'])->name('customer.backups.policy.update');
        Route::get('monitoring', [MonitoringController::class, 'index'])->name('customer.monitoring.index');
        Route::get('monitoring/servers/{virtualMachine}/metrics', [MonitoringController::class, 'metrics'])->name('customer.monitoring.metrics');
        Route::get('wallet', [CustomerWalletController::class, 'show'])->name('customer.wallet.show');
        Route::post('wallet/top-ups', [PaymentController::class, 'storeTopUp'])->name('customer.wallet.topups.store');
        Route::get('wallet/payments/{payment}/gateway', [PaymentController::class, 'showGateway'])->name('customer.wallet.payments.gateway.show');
        Route::post('wallet/payments/{payment}/gateway', [PaymentController::class, 'submitGateway'])->name('customer.wallet.payments.gateway.store');
        Route::get('wallet/payments/{payment}/callback', [PaymentController::class, 'callback'])->name('customer.wallet.payments.callback');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('customer.invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('customer.invoices.show');
    });
});

Route::get('/api/console-proxy/sessions/{session}', [ServerConsoleController::class, 'proxySession'])
    ->name('console-proxy.sessions.show');

Route::get('/', function (WalletService $wallets) {
    return view('home', [
        'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
        'wallets' => $wallets,
    ]);
})->name('home');

Route::get('/pricing', function (WalletService $wallets) {
    return view('pricing', [
        'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
        'wallets' => $wallets,
    ]);
})->name('pricing');

Route::get('/solutions', function (WalletService $wallets) {
    return view('solutions', [
        'bundles' => VmBundle::query()->where('is_active', true)->orderBy('sort_order')->orderBy('monthly_price')->get(),
        'wallets' => $wallets,
    ]);
})->name('solutions');

Route::get('/changelog', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }

    if (Auth::guard('customer')->check()) {
        return redirect()->route('dashboard');
    }

    return view('changelog');
})->name('changelog');
Route::view('/contact', 'contact')->name('contact');
