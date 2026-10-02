<?php

namespace App\Providers;

use App\Models\VirtualMachine;
use App\Services\MeteringInventoryService;
use App\Services\Payments\DummyPaymentGateway;
use App\Services\Payments\HesabroPaymentGateway;
use App\Services\Payments\MellatClientInterface;
use App\Services\Payments\MellatPaymentGateway;
use App\Services\Payments\MellatSoapClient;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\Payments\ZibalPaymentGateway;
use App\Support\AdminAccess;
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
