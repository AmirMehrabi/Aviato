<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Payment;
use App\Services\Payments\PaymentGatewayInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly PaymentGatewayInterface $gateway,
    ) {}

    public function createTopUp(Customer $customer, int $amount, ?string $description = null): Payment
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $wallet = $this->wallets->walletFor($customer);
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'provider' => config('payments.default', 'dummy'),
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING,
            'amount' => $amount,
            'currency' => AppSetting::currency(),
            'authority' => $this->newAuthority(),
            'description' => $description ?: 'شارژ کیف پول مشتری',
        ]);

        $payload = $this->gateway->initiate($payment);
        $payment->forceFill([
            'gateway_payload' => $payload,
            'authority' => $payload['authority'] ?? $payment->authority,
        ])->save();

        return $payment->refresh();
    }

    public function completeTopUp(Payment $payment, array $payload = []): Payment
    {
        return DB::transaction(function () use ($payment, $payload): Payment {
            $payment = Payment::query()->lockForUpdate()->findOrFail($payment->id);

            if ($payment->isSuccessful()) {
                return $payment;
            }

            if (! $payment->isPending()) {
                throw ValidationException::withMessages(['payment' => 'Payment can no longer be completed.']);
            }

            $result = $this->gateway->complete($payment, $payload);

            $payment->forceFill([
                'status' => Payment::STATUS_SUCCESSFUL,
                'provider_reference' => $result['provider_reference'] ?? $payment->provider_reference,
                'gateway_payload' => array_merge($payment->gateway_payload ?? [], $result['payload'] ?? [], ['completed_at' => now()->toIso8601String()]),
                'paid_at' => now(),
                'failed_at' => null,
            ])->save();

            $this->wallets->credit(
                $payment->customer,
                $payment->amount,
                'شارژ کیف پول از طریق درگاه آزمایشی',
                reference: $payment,
                metadata: [
                    'category' => 'wallet_top_up',
                    'provider' => $payment->provider,
                    'payment_id' => $payment->id,
                    'authority' => $payment->authority,
                    'provider_reference' => $payment->provider_reference,
                ],
            );

            return $payment->refresh();
        });
    }

    public function failTopUp(Payment $payment, array $payload = []): Payment
    {
        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
            'gateway_payload' => array_merge($payment->gateway_payload ?? [], $payload),
        ])->save();

        return $payment;
    }

    private function newAuthority(): string
    {
        do {
            $authority = strtoupper(Str::random(16));
        } while (Payment::query()->where('authority', $authority)->exists());

        return $authority;
    }
}
