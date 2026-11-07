<?php

namespace App\Services\Payments;

use App\Models\AppSetting;
use App\Models\Customer;
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

    public function labelFor(string $provider): string
    {
        $class = $this->gateways[$provider] ?? null;

        return $class === null
            ? $provider
            : $this->container->make($class)->label();
    }

    /**
     * @return array<string, string>
     */
    public function labels(): array
    {
        return collect($this->gateways)
            ->mapWithKeys(fn (string $class, string $provider): array => [
                $provider => $this->container->make($class)->label(),
            ])
            ->all();
    }

    /**
     * @return array<string, string>
     */
    public function available(?Customer $customer = null): array
    {
        if (! AppSetting::paymentsEnabled()) {
            return [];
        }

        $publicGateways = config('payments.public_gateways');
        $allowed = $customer?->isWalletTestCustomer()
            ? ['hesabro']
            : ($publicGateways === null ? array_keys($this->gateways) : array_map('trim', explode(',', $publicGateways)));

        return collect($this->gateways)
            ->only($allowed)
            ->mapWithKeys(function (string $class, string $provider): array {
                $gateway = $this->container->make($class);

                return $gateway->isAvailable()
                    ? [$provider => $gateway->label()]
                    : [];
            })
            ->all();
    }
}
