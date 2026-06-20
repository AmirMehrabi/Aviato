<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGatewayInterface
{
    public function key(): string;

    public function label(): string;

    public function isAvailable(): bool;

    /**
     * @return array<string, mixed>
     */
    public function initiate(Payment $payment): array;

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    public function complete(Payment $payment, array $payload = []): array;
}
