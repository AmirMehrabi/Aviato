<?php

namespace App\Services\Payments;

use App\Models\Payment;

class DummyPaymentGateway implements PaymentGatewayInterface
{
    public function key(): string
    {
        return 'dummy';
    }

    public function label(): string
    {
        return 'درگاه آزمایشی';
    }

    public function isAvailable(): bool
    {
        return false;
    }

    public function initiate(Payment $payment): array
    {
        return [
            'authority' => $payment->authority,
            'status' => 'pending',
        ];
    }

    public function complete(Payment $payment, array $payload = []): array
    {
        return [
            'provider_reference' => 'DUMMY-'.str_pad((string) $payment->id, 8, '0', STR_PAD_LEFT),
            'status' => 'successful',
            'payload' => $payload,
        ];
    }
}
