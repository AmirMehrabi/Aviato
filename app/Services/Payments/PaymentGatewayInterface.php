<?php

namespace App\Services\Payments;

use App\Models\Payment;

interface PaymentGatewayInterface
{
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
