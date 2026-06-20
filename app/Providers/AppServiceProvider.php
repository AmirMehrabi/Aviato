<?php

namespace App\Providers;

use App\Services\Payments\DummyPaymentGateway;
use App\Services\Payments\HesabroPaymentGateway;
use App\Services\Payments\MellatClientInterface;
use App\Services\Payments\MellatPaymentGateway;
use App\Services\Payments\MellatSoapClient;
use App\Services\Payments\PaymentGatewayManager;
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
                'dummy' => DummyPaymentGateway::class,
            ]);
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
