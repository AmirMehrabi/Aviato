<?php

namespace App\Providers;

use App\Models\AdminDashboardWarningPreference;
use App\Models\AppSetting;
use App\Models\NetworkUsageBucket;
use App\Models\Payment;
use App\Models\ProxmoxServer;
use App\Models\ResourceRate;
use App\Models\Ticket;
use App\Models\UsageSettlement;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBundle;
use App\Models\VmBundleLocationMapping;
use App\Models\VmDisk;
use App\Models\VmNetworkBillingPeriod;
use App\Models\VmUpgradeOrder;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\MeteringInventoryService;
use App\Services\Payments\DummyPaymentGateway;
use App\Services\Payments\HesabroPaymentGateway;
use App\Services\Payments\MellatClientInterface;
use App\Services\Payments\MellatPaymentGateway;
use App\Services\Payments\MellatSoapClient;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\ZibalPaymentGateway;
use App\Services\PortalCache;
use App\Support\AdminAccess;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(MellatClientInterface::class, MellatSoapClient::class);
        $this->app->singleton(PaymentGatewayManager::class, function ($app): PaymentGatewayManager {
            return new PaymentGatewayManager($app, [
                'mellat' => MellatPaymentGateway::class,
                'hesabro' => HesabroPaymentGateway::class,
                'zibal' => ZibalPaymentGateway::class,
                'dummy' => DummyPaymentGateway::class,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Rotate immediately for this process and again after commit, so a reader
        // cannot retain a snapshot built before the transaction became visible.
        $invalidate = function (Model $model, array $scopes): void {
            app(PortalCache::class)->invalidate($scopes);
            if ($model->getConnection()->transactionLevel() > 0) {
                $model->getConnection()->afterCommit(fn () => app(PortalCache::class)->invalidate($scopes));
            }
        };
        foreach ([
            Payment::class => ['admin'],
            Wallet::class => ['admin'],
            WalletTransaction::class => ['admin'],
            UsageSettlement::class => ['admin'],
            VirtualMachine::class => ['admin', 'customer'],
            VmDisk::class => ['customer'],
            VmBackup::class => ['admin'],
            VmUpgradeOrder::class => ['admin'],
            ProxmoxServer::class => ['admin'],
            Ticket::class => ['admin'],
            ResourceRate::class => ['customer'],
            VmBundle::class => ['customer'],
            VmBundleLocationMapping::class => ['customer'],
            AppSetting::class => ['admin', 'customer'],
            VmNetworkBillingPeriod::class => ['network'],
            NetworkUsageBucket::class => ['network'],
            AdminDashboardWarningPreference::class => ['admin'],
        ] as $model => $scopes) {
            $model::saved(fn (Model $record) => $invalidate($record, $scopes));
            $model::deleted(fn (Model $record) => $invalidate($record, $scopes));
        }

        Blade::if('adminAbility', fn (string $ability): bool => auth('admin')->user()?->allows($ability) ?? false);
        Blade::if('adminRoute', fn (string $name): bool => auth('admin')->user() && AdminAccess::canVisit(auth('admin')->user(), $name));

        $sync = function (VirtualMachine $vm): void {
            if (Schema::hasTable('metering_inventory_assignments')) {
                app(MeteringInventoryService::class)->syncVm($vm->fresh()->load('reservedIpAddress'));
            }
        };
        VirtualMachine::saved($sync);
        VirtualMachine::deleting(function (VirtualMachine $vm): void {
            if (Schema::hasTable('metering_inventory_assignments')) {
                app(MeteringInventoryService::class)->closeVm($vm);
            }
        });
    }
}
