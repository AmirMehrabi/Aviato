<?php

use App\Http\Controllers\Admin\ApiActivityController;
use App\Http\Controllers\Admin\BillingController;
use App\Http\Controllers\Admin\CloudImageController;
use App\Http\Controllers\Admin\CustomerController;
use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Admin\HetznerAccountController;
use App\Http\Controllers\Admin\InfrastructureLocationController;
use App\Http\Controllers\Admin\IpPoolController;
use App\Http\Controllers\Admin\NotificationController;
use App\Http\Controllers\Admin\ProjectController as AdminProjectController;
use App\Http\Controllers\Admin\ProxmoxServerWebController;
use App\Http\Controllers\Admin\ResellerController;
use App\Http\Controllers\Admin\ResourceRateController;
use App\Http\Controllers\Admin\SearchController;
use App\Http\Controllers\Admin\SettingController;
use App\Http\Controllers\Admin\SupportTeamController;
use App\Http\Controllers\Admin\TicketAttachmentController as AdminTicketAttachmentController;
use App\Http\Controllers\Admin\TicketCategoryController;
use App\Http\Controllers\Admin\TicketController as AdminTicketController;
use App\Http\Controllers\Admin\VirtualMachineConsoleController;
use App\Http\Controllers\Admin\VirtualMachineController;
use App\Http\Controllers\Admin\VmBundleController;
use App\Http\Controllers\Admin\WalletController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\Auth\CustomerEmailVerificationController;
use App\Http\Controllers\Auth\CustomerImpersonationController;
use App\Http\Controllers\Auth\CustomerOtpLoginController;
use App\Http\Controllers\Auth\CustomerPasswordResetController;
use App\Http\Controllers\Auth\RegisteredUserController;
use App\Http\Controllers\BlogController;
use App\Http\Controllers\ContactController;
use App\Http\Controllers\Customer\ApiTokenController;
use App\Http\Controllers\Customer\BackupController;
use App\Http\Controllers\Customer\DashboardController;
use App\Http\Controllers\Customer\InvoiceController;
use App\Http\Controllers\Customer\MonitoringController;
use App\Http\Controllers\Customer\PaymentController;
use App\Http\Controllers\Customer\ProfileController;
use App\Http\Controllers\Customer\ProjectController;
use App\Http\Controllers\Customer\ResellerController as CustomerResellerController;
use App\Http\Controllers\Customer\ServerConsoleController;
use App\Http\Controllers\Customer\ServerController;
use App\Http\Controllers\Customer\StorageController;
use App\Http\Controllers\Customer\TicketAttachmentController;
use App\Http\Controllers\Customer\TicketController;
use App\Http\Controllers\Customer\VmUpgradeController;
use App\Http\Controllers\Customer\WalletController as CustomerWalletController;
use App\Http\Controllers\S3GatewayController;
use App\Http\Controllers\SitemapController;
use App\Models\VmBundle;
use App\Services\WalletService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

$adminDomain = config('portals.admin.domain');
$customerDomain = config('portals.customer.domain');
$customerAliasDomains = collect(config('portals.customer.aliases', []))
    ->filter()
    ->reject(fn (string $domain): bool => $domain === $customerDomain)
    ->unique()
    ->values();

$adminLogin = config('portals.admin.login_path');
$adminHome = config('portals.admin.home_path');
$customerLogin = config('portals.customer.login_path');
$customerRegister = config('portals.customer.register_path');
$customerHome = config('portals.customer.home_path');

Route::domain(config('storage.s3_domain'))
    ->middleware(['api.audit', 'throttle:120,1'])
    ->withoutMiddleware([
        StartSession::class,
        ShareErrorsFromSession::class,
        PreventRequestForgery::class,
    ])
    ->group(function (): void {
        Route::any('{bucket?}/{key?}', S3GatewayController::class)
            ->where('key', '.*')
            ->name('s3.gateway');
    });

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
        Route::get($adminHome, AdminDashboardController::class)->name('admin.dashboard');
        Route::post('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])
            ->name('admin.notifications.mark-all-read');
        Route::post('notifications/{notification}/read', [NotificationController::class, 'markRead'])
            ->name('admin.notifications.read');

        Route::get('search', [SearchController::class, '__invoke'])->name('admin.search');

        Route::get('settings', [SettingController::class, 'edit'])->name('admin.settings.edit');
        Route::get('settings/{section}', [SettingController::class, 'section'])
            ->whereIn('section', ['general', 'billing', 'payments', 'verification', 'sms', 'email', 'tickets', 'protection'])
            ->name('admin.settings.section');
        Route::get('api-activity', [ApiActivityController::class, 'index'])->name('admin.api-activity.index');
        Route::patch('settings', [SettingController::class, 'update'])->name('admin.settings.update');
        Route::patch('settings/{section}', [SettingController::class, 'updateSection'])
            ->whereIn('section', ['general', 'billing', 'payments', 'verification', 'sms', 'email', 'tickets', 'protection'])
            ->name('admin.settings.section.update');
        Route::post('hetzner-accounts/{hetznerAccount}/test', [HetznerAccountController::class, 'test'])
            ->name('admin.hetzner-accounts.test');
        Route::post('hetzner-accounts/{hetznerAccount}/sync', [HetznerAccountController::class, 'sync'])
            ->name('admin.hetzner-accounts.sync');
        Route::resource('hetzner-accounts', HetznerAccountController::class)
            ->parameters(['hetzner-accounts' => 'hetznerAccount'])
            ->names('admin.hetzner-accounts');
        Route::resource('infrastructure-locations', InfrastructureLocationController::class)
            ->parameters(['infrastructure-locations' => 'location'])
            ->only(['index', 'edit', 'update'])
            ->names('admin.infrastructure-locations');

        Route::post('tickets/{ticket}/reply', [AdminTicketController::class, 'reply'])->name('admin.tickets.reply');
        Route::patch('tickets/{ticket}/assignment', [AdminTicketController::class, 'assignment'])->name('admin.tickets.assignment');
        Route::patch('tickets/{ticket}/status', [AdminTicketController::class, 'status'])->name('admin.tickets.status');
        Route::get('tickets/{ticket}/attachments/{attachment}', [AdminTicketAttachmentController::class, 'show'])->name('admin.tickets.attachments.show');
        Route::resource('tickets', AdminTicketController::class)->only(['index', 'create', 'store', 'show'])->names('admin.tickets');
        Route::resource('support-teams', SupportTeamController::class)
            ->parameters(['support-teams' => 'supportTeam'])
            ->only(['index', 'store', 'update'])
            ->names('admin.support-teams');
        Route::resource('ticket-categories', TicketCategoryController::class)
            ->parameters(['ticket-categories' => 'category'])
            ->only(['index', 'store', 'update'])
            ->names('admin.ticket-categories');

        Route::post('customers/{customer}/wallet-transactions', [WalletController::class, 'storeTransaction'])
            ->name('admin.customers.wallet-transactions.store');
        Route::patch('customers/{customer}/wallet-lock', [WalletController::class, 'updateLock'])
            ->name('admin.customers.wallet-lock.update');
        Route::patch('customers/{customer}/sms-notifications', [CustomerController::class, 'updateSmsNotifications'])
            ->name('admin.customers.sms-notifications.update');

        Route::patch('customers/{customer}/suspend', [CustomerController::class, 'suspend'])
            ->name('admin.customers.suspend');
        Route::patch('customers/{customer}/activate', [CustomerController::class, 'activate'])
            ->name('admin.customers.activate');
        Route::post('customers/{customer}/impersonate', [CustomerController::class, 'impersonate'])
            ->name('admin.customers.impersonate');
        Route::resource('customers', CustomerController::class)
            ->names('admin.customers');

        Route::get('workspaces', [AdminProjectController::class, 'index'])->name('admin.projects.index');
        Route::get('workspaces/{project}', [AdminProjectController::class, 'show'])->name('admin.projects.show');
        Route::get('workspaces/{project}/proforma', [AdminProjectController::class, 'proforma'])->name('admin.projects.proforma');
        Route::patch('workspaces/{project}', [AdminProjectController::class, 'update'])->name('admin.projects.update');
        Route::post('workspaces/{project}/members', [AdminProjectController::class, 'storeMember'])->name('admin.projects.members.store');
        Route::patch('workspaces/{project}/members/{member}', [AdminProjectController::class, 'updateMember'])->name('admin.projects.members.update');
        Route::delete('workspaces/{project}/members/{member}', [AdminProjectController::class, 'destroyMember'])->name('admin.projects.members.destroy');

        Route::get('virtual-machines/proxmox-servers/{proxmoxServer}/options', [VirtualMachineController::class, 'options'])
            ->name('admin.virtual-machines.options');
        Route::post('virtual-machines/move-node', [VirtualMachineController::class, 'moveNode'])
            ->name('admin.virtual-machines.move-node');
        Route::post('virtual-machines/{virtualMachine}/start', [VirtualMachineController::class, 'start'])
            ->name('admin.virtual-machines.start');
        Route::post('virtual-machines/{virtualMachine}/stop', [VirtualMachineController::class, 'stop'])
            ->name('admin.virtual-machines.stop');
        Route::patch('virtual-machines/{virtualMachine}/ip-address', [VirtualMachineController::class, 'updateIpAddress'])
            ->name('admin.virtual-machines.ip-address.update');
        Route::post('virtual-machines/{virtualMachine}/retry-provisioning', [VirtualMachineController::class, 'retryProvisioning'])
            ->name('admin.virtual-machines.retry-provisioning');
        Route::get('virtual-machines/{virtualMachine}/transfer', [VirtualMachineController::class, 'showTransferForm'])
            ->name('admin.virtual-machines.transfer.show');
        Route::post('virtual-machines/{virtualMachine}/transfer', [VirtualMachineController::class, 'transfer'])
            ->name('admin.virtual-machines.transfer');
        Route::get('virtual-machines/{virtualMachine}/console', [VirtualMachineConsoleController::class, 'show'])
            ->name('admin.virtual-machines.console.show');
        Route::post('virtual-machines/{virtualMachine}/console/session', [VirtualMachineConsoleController::class, 'session'])
            ->name('admin.virtual-machines.console.session');
        Route::resource('virtual-machines', VirtualMachineController::class)
            ->parameters(['virtual-machines' => 'virtualMachine'])
            ->names('admin.virtual-machines');
        Route::resource('cloud-images', CloudImageController::class)
            ->parameters(['cloud-images' => 'cloudImage'])
            ->except(['show'])
            ->names('admin.cloud-images');
        Route::post('ip-pools/{ipPool}/addresses/reserve', [IpPoolController::class, 'reserveAddresses'])
            ->name('admin.ip-pools.addresses.reserve');
        Route::post('ip-pools/{ipPool}/addresses/{ipAddress}/reserve', [IpPoolController::class, 'reserveAddress'])
            ->name('admin.ip-pools.addresses.reserve-one');
        Route::post('ip-pools/{ipPool}/addresses/{ipAddress}/release', [IpPoolController::class, 'releaseAddress'])
            ->name('admin.ip-pools.addresses.release');
        Route::resource('ip-pools', IpPoolController::class)
            ->parameters(['ip-pools' => 'ipPool'])
            ->names('admin.ip-pools');

        Route::prefix('billing')->name('admin.billing.')->group(function (): void {
            Route::get('/', [BillingController::class, 'overview'])->name('overview');
            Route::get('payments', [BillingController::class, 'payments'])->name('payments.index');
            Route::get('payments/{payment}', [BillingController::class, 'payment'])->name('payments.show');
            Route::get('transactions', [BillingController::class, 'transactions'])->name('transactions.index');
            Route::get('transactions/{transaction}', [BillingController::class, 'transaction'])->name('transactions.show');
            Route::get('invoices', [BillingController::class, 'invoices'])->name('invoices.index');
            Route::get('invoices/{invoice}', [BillingController::class, 'invoice'])->name('invoices.show');
            Route::get('usage', [BillingController::class, 'usage'])->name('usage.index');
            Route::get('usage/{settlement}', [BillingController::class, 'settlement'])->name('usage.show');
            Route::get('wallets', [BillingController::class, 'wallets'])->name('wallets.index');
            Route::get('exports/{ledger}', [BillingController::class, 'export'])->name('exports');
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

        Route::prefix('resellers')->name('admin.resellers.')->group(function (): void {
            Route::get('/', [ResellerController::class, 'index'])->name('index');
            Route::get('/create', [ResellerController::class, 'create'])->name('create');
            Route::post('/', [ResellerController::class, 'store'])->name('store');
            Route::get('/withdrawals', [ResellerController::class, 'withdrawals'])->name('withdrawals');
            Route::patch('/withdrawals/{withdrawal}/approve', [ResellerController::class, 'approveWithdrawal'])->name('withdrawals.approve');
            Route::patch('/withdrawals/{withdrawal}/reject', [ResellerController::class, 'rejectWithdrawal'])->name('withdrawals.reject');
            Route::patch('/withdrawals/{withdrawal}/paid', [ResellerController::class, 'markWithdrawalPaid'])->name('withdrawals.paid');
            Route::get('/{customer}', [ResellerController::class, 'show'])->name('show');
            Route::put('/{customer}', [ResellerController::class, 'update'])->name('update');
            Route::delete('/{customer}', [ResellerController::class, 'destroy'])->name('destroy');
            Route::patch('/{customer}/suspend', [ResellerController::class, 'suspend'])->name('suspend');
            Route::patch('/{customer}/activate', [ResellerController::class, 'activate'])->name('activate');
            Route::post('/{customer}/assign', [ResellerController::class, 'assignCustomer'])->name('assign');
            Route::delete('/{customer}/assign/{targetCustomer}', [ResellerController::class, 'unassignCustomer'])->name('unassign');
        });
    });
});

$customerRoutes = function () use ($customerLogin, $customerRegister, $customerHome): void {
    Route::get('impersonate/{token}', CustomerImpersonationController::class)
        ->where('token', '[A-Za-z0-9]{64}')
        ->middleware('throttle:20,1')
        ->name('customer.impersonation.accept');

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

        Route::get('login/otp', [CustomerOtpLoginController::class, 'requestForm'])
            ->name('customer.login.otp');
        Route::post('login/otp', [CustomerOtpLoginController::class, 'sendCode'])
            ->name('customer.login.otp.send');
        Route::get('login/otp/verify', [CustomerOtpLoginController::class, 'form'])
            ->name('customer.login.otp.verify');
        Route::post('login/otp/verify', [CustomerOtpLoginController::class, 'verify'])
            ->name('customer.login.otp.verify.store');
        Route::post('login/otp/resend', [CustomerOtpLoginController::class, 'resend'])
            ->name('customer.login.otp.resend');

        Route::get('password/forgot', [CustomerPasswordResetController::class, 'requestForm'])
            ->name('customer.password.request');
        Route::post('password/forgot', [CustomerPasswordResetController::class, 'sendCode'])
            ->name('customer.password.send');
        Route::get('password/otp', [CustomerPasswordResetController::class, 'otpForm'])
            ->name('customer.password.otp');
        Route::post('password/otp', [CustomerPasswordResetController::class, 'verifyCode'])
            ->name('customer.password.verify');
        Route::get('password/reset', [CustomerPasswordResetController::class, 'resetForm'])
            ->name('customer.password.reset');
        Route::post('password/reset', [CustomerPasswordResetController::class, 'resetPassword'])
            ->name('customer.password.update');

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

    Route::get('suspended', [CustomerWalletController::class, 'suspensionNotice'])
        ->middleware('auth:customer')
        ->name('customer.suspension.notice');

    Route::match(['GET', 'POST'], 'wallet/payments/{payment}/callback', [PaymentController::class, 'callback'])
        ->withoutMiddleware([
            StartSession::class,
            ShareErrorsFromSession::class,
            PreventRequestForgery::class,
        ])
        ->name('customer.wallet.payments.callback');

    Route::middleware(['auth:customer', 'customer.wallet.access'])->group(function () use ($customerHome) {
        Route::get($customerHome, DashboardController::class)->name('dashboard');
        Route::get('profile', [ProfileController::class, 'show'])->name('customer.profile.show');
        Route::post('profile/api-tokens', [ApiTokenController::class, 'store'])->name('customer.profile.api-tokens.store');
        Route::delete('profile/api-tokens/{token}', [ApiTokenController::class, 'destroy'])->name('customer.profile.api-tokens.destroy');
        Route::patch('profile/national-code', [ProfileController::class, 'updateNationalCode'])->name('customer.profile.national-code.update');
        Route::get('storage', [StorageController::class, 'index'])->name('customer.storage.index');
        Route::post('storage/buckets', [StorageController::class, 'storeBucket'])->name('customer.storage.buckets.store');
        Route::delete('storage/buckets/{bucket}', [StorageController::class, 'destroyBucket'])->name('customer.storage.buckets.destroy');
        Route::post('storage/access-keys', [StorageController::class, 'storeAccessKey'])->name('customer.storage.access-keys.store');
        Route::delete('storage/access-keys/{key}', [StorageController::class, 'destroyAccessKey'])->name('customer.storage.access-keys.destroy');
        Route::post('projects/switch', [ProjectController::class, 'switch'])->name('customer.projects.switch');
        Route::get('projects', [ProjectController::class, 'index'])->name('customer.projects.index');
        Route::post('projects', [ProjectController::class, 'store'])->name('customer.projects.store');
        Route::get('projects/{project}', [ProjectController::class, 'show'])->name('customer.projects.show');
        Route::patch('projects/{project}', [ProjectController::class, 'update'])->name('customer.projects.update');
        Route::post('projects/{project}/members', [ProjectController::class, 'storeMember'])->name('customer.projects.members.store');
        Route::patch('projects/{project}/members/{member}', [ProjectController::class, 'updateMember'])->name('customer.projects.members.update');
        Route::delete('projects/{project}/members/{member}', [ProjectController::class, 'destroyMember'])->name('customer.projects.members.destroy');

        Route::middleware('customer.vm.access')->group(function (): void {
            Route::get('servers', [ServerController::class, 'index'])->name('customer.servers.index');
            Route::get('servers/create', [ServerController::class, 'create'])->name('customer.servers.create');
            Route::post('servers', [ServerController::class, 'store'])->name('customer.servers.store');
            Route::get('servers/statuses', [ServerController::class, 'statuses'])->name('customer.servers.statuses');
            Route::get('servers/{virtualMachine}/console', [ServerConsoleController::class, 'show'])->name('customer.servers.console.show');
            Route::get('servers/{virtualMachine}/console/session', [ServerConsoleController::class, 'redirectSession'])->name('customer.servers.console.session.redirect');
            Route::post('servers/{virtualMachine}/console/session', [ServerConsoleController::class, 'session'])->name('customer.servers.console.session');
            Route::post('servers/{virtualMachine}/rebuild', [ServerController::class, 'rebuild'])->name('customer.servers.rebuild');
            Route::get('servers/{virtualMachine}', [ServerController::class, 'show'])->name('customer.servers.show');
            Route::post('servers/{virtualMachine}/upgrades/bundle', [VmUpgradeController::class, 'storeBundle'])->name('customer.servers.upgrades.bundle.store');
            Route::post('servers/{virtualMachine}/upgrades/extra-disk', [VmUpgradeController::class, 'storeExtraDisk'])->name('customer.servers.upgrades.extra-disk.store');
            Route::delete('servers/{virtualMachine}', [ServerController::class, 'destroy'])->name('customer.servers.destroy');
            Route::get('backups', [BackupController::class, 'index'])->name('customer.backups.index');
            Route::post('backups/servers/{virtualMachine}', [BackupController::class, 'storeManual'])->name('customer.backups.manual.store');
            Route::patch('backups/servers/{virtualMachine}/policy', [BackupController::class, 'updatePolicy'])->name('customer.backups.policy.update');
            Route::get('monitoring', [MonitoringController::class, 'index'])->name('customer.monitoring.index');
            Route::get('monitoring/servers/{virtualMachine}/metrics', [MonitoringController::class, 'metrics'])->name('customer.monitoring.metrics');
        });
        Route::post('tickets/{ticket}/reply', [TicketController::class, 'reply'])->name('customer.tickets.reply');
        Route::patch('tickets/{ticket}/close', [TicketController::class, 'close'])->name('customer.tickets.close');
        Route::patch('tickets/{ticket}/reopen', [TicketController::class, 'reopen'])->name('customer.tickets.reopen');
        Route::get('tickets/{ticket}/attachments/{attachment}', [TicketAttachmentController::class, 'show'])->name('customer.tickets.attachments.show');
        Route::resource('tickets', TicketController::class)->only(['index', 'create', 'store', 'show'])->names('customer.tickets');
        Route::get('wallet', [CustomerWalletController::class, 'show'])->name('customer.wallet.show');
        Route::post('wallet/top-ups', [PaymentController::class, 'storeTopUp'])->name('customer.wallet.topups.store');
        Route::get('wallet/payments/{payment}/gateway', [PaymentController::class, 'showGateway'])->name('customer.wallet.payments.gateway.show');
        Route::post('wallet/payments/{payment}/gateway', [PaymentController::class, 'submitGateway'])->name('customer.wallet.payments.gateway.store');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('customer.invoices.index');
        Route::get('invoices/{invoice}', [InvoiceController::class, 'show'])->name('customer.invoices.show');

        Route::prefix('reseller')->name('customer.reseller.')->middleware('reseller.active')->group(function (): void {
            Route::get('/', [CustomerResellerController::class, 'index'])->name('dashboard');
            Route::get('/customers', [CustomerResellerController::class, 'customers'])->name('customers');
            Route::get('/commissions', [CustomerResellerController::class, 'commissions'])->name('commissions');
            Route::get('/referral', [CustomerResellerController::class, 'referralLink'])->name('referral');
            Route::get('/withdrawals', [CustomerResellerController::class, 'withdrawals'])->name('withdrawals');
            Route::post('/withdrawals', [CustomerResellerController::class, 'storeWithdrawal'])->name('withdrawals.store');
        });
    });
};

Route::domain($customerDomain)->middleware('portal.host:customer')->group($customerRoutes);

foreach ($customerAliasDomains as $domain) {
    $aliasName = 'customer.alias.'.str_replace(['.', '-'], '_', $domain).'.';

    Route::domain($domain)
        ->name($aliasName)
        ->middleware('portal.host:customer')
        ->group($customerRoutes);
}

Route::get('/', function (WalletService $wallets) {
    $postsPath = resource_path('blog/posts');
    $files = glob($postsPath.'/*.md');
    $posts = [];

    foreach ($files as $file) {
        $content = file_get_contents($file);
        if (preg_match('/^---\n(.+?)\n---\n(.+)$/s', $content, $matches)) {
            $meta = [];
            foreach (explode("\n", $matches[1]) as $line) {
                if (preg_match('/^(\w+):\s*"?(.+?)"?$/', trim($line), $m)) {
                    $meta[$m[1]] = $m[2];
                }
            }
            $posts[] = [
                'title' => $meta['title'] ?? '',
                'slug' => $meta['slug'] ?? '',
                'excerpt' => $meta['excerpt'] ?? '',
                'category' => $meta['category'] ?? '',
                'date' => $meta['date'] ?? '',
                'date_display' => $meta['date_display'] ?? '',
                'reading_time' => $meta['reading_time'] ?? '',
            ];
        }
    }

    usort($posts, fn ($a, $b) => $b['date'] <=> $a['date']);

    return view('home', [
        'bundles' => VmBundle::query()
            ->where('is_active', true)
            ->where('show_on_marketing', true)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get(),
        'wallets' => $wallets,
        'latestPosts' => array_slice($posts, 0, 3),
    ]);
})->name('home');

Route::get('/pricing', function (WalletService $wallets) {
    return view('pricing', [
        'bundles' => VmBundle::query()
            ->where('is_active', true)
            ->where('show_on_marketing', true)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get(),
        'wallets' => $wallets,
    ]);
})->name('pricing');

Route::get('/solutions', function (WalletService $wallets) {
    return view('solutions', [
        'bundles' => VmBundle::query()
            ->where('is_active', true)
            ->where('show_on_marketing', true)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->get(),
        'wallets' => $wallets,
    ]);
})->name('solutions');

Route::get('/solutions/co-location', fn () => view('solutions-colocation'))
    ->name('solutions.colocation');

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::get('/changelog', function () {
    if (Auth::guard('admin')->check()) {
        return redirect()->route('admin.dashboard');
    }

    if (Auth::guard('customer')->check()) {
        return redirect()->route('dashboard');
    }

    return view('changelog');
})->name('changelog');
Route::get('/blog', [BlogController::class, 'index'])->name('blog');
Route::get('/blog/{slug}', [BlogController::class, 'show'])->name('blog.show');

Route::get('/contact', [ContactController::class, 'create'])->name('contact');
Route::post('/contact', [ContactController::class, 'store'])->name('contact.store');

Route::get('/api-docs', fn () => view('api-docs'))->name('api.documentation');
