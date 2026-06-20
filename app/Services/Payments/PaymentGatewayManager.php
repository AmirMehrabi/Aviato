<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use Illuminate\Contracts\Container\Container;
use Illuminate\Validation\ValidationException;

class PaymentGatewayManager
{
    /**
     * @param  array<string, class-string<PaymentGatewayInterface>>  $gateways
     */
    public function __construct(
        private readonly Container $container,
        private readonly array $gateways,
    ) {}

    public function gateway(string $provider): PaymentGatewayInterface
    {
        $class = $this->gateways[$provider] ?? null;

        if ($class === null) {
            throw ValidationException::withMessages([
                'gateway' => 'درگاه پرداخت انتخاب‌شده معتبر نیست.',
            ]);
        }

        return $this->container->make($class);
    }

    /**
     * @return array<string, string>
     */
    public function available(): array
    {
        if (! AppSetting::paymentsEnabled()) {
            return [];
        }

        return collect($this->gateways)
            ->mapWithKeys(function (string $class, string $provider): array {
                $gateway = $this->container->make($class);

                return $gateway->isAvailable()
                    ? [$provider => $gateway->label()]
                    : [];
            })
            ->all();
    }
}
