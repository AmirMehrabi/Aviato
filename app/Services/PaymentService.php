<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\Project;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PaymentService
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly PaymentGatewayManager $gateways,
        private readonly PromotionService $promotions,
    ) {}

    public function createTopUp(Customer $customer, int $amount, ?string $description = null, ?string $provider = null, ?string $promotionCode = null, ?Customer $redeemer = null, ?Project $project = null, ?Request $request = null): Payment
    {
        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $provider ??= AppSetting::defaultPaymentGateway();
        $availableGateways = $this->gateways->available();

        if (! array_key_exists($provider, $availableGateways)) {
            throw ValidationException::withMessages([
                'gateway' => 'درگاه پرداخت انتخاب‌شده در حال حاضر فعال و آماده نیست.',
            ]);
        }

        $wallet = $this->wallets->walletFor($customer);
        $payment = Payment::create([
            'customer_id' => $customer->id,
            'wallet_id' => $wallet->id,
            'provider' => $provider,
            'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING,
            'amount' => $amount,
            'currency' => AppSetting::currency(),
            'authority' => $this->newAuthority(),
            'description' => $description ?: 'شارژ کیف پول مشتری',
        ]);

        if ($promotionCode !== null && $promotionCode !== '') {
            if (! $redeemer || ! $project) {
                $payment->delete();
                throw ValidationException::withMessages(['promotion_code' => 'اطلاعات فضای کاری برای کد هدیه کامل نیست.']);
            }
            try {
                $this->promotions->reserveForPayment($payment, $promotionCode, $redeemer, $project, $request);
            } catch (ValidationException $exception) {
                $payment->delete();
                throw $exception;
            }
        }

        $gateway = $this->gateways->gateway($provider);

        try {
            $payload = $gateway->initiate($payment);
        } catch (PaymentGatewayException|ValidationException $exception) {
            $this->promotions->releasePaymentReservation($payment, 'gateway_initiation_failed');
            $this->failTopUp($payment, [
                'initiation_failed_at' => now()->toIso8601String(),
                'failed_reason' => $exception->getMessage(),
            ]);

            throw $exception;
        }

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

            $gateway = $this->gateways->gateway($payment->provider);
            $result = $gateway->complete($payment, $payload);

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
                'شارژ کیف پول از طریق '.$gateway->label(),
                reference: $payment,
                metadata: [
                    'category' => 'wallet_top_up',
                    'provider' => $payment->provider,
                    'payment_id' => $payment->id,
                    'authority' => $payment->authority,
                    'provider_reference' => $payment->provider_reference,
                ],
            );

            $this->promotions->completePaymentBonus($payment);

            return $payment->refresh();
        });
    }

    public function failTopUp(Payment $payment, array $payload = []): Payment
    {
        $this->promotions->releasePaymentReservation($payment, 'payment_failed');
        $payment->forceFill([
            'status' => Payment::STATUS_FAILED,
            'failed_at' => now(),
            'gateway_payload' => array_merge($payment->gateway_payload ?? [], $payload),
        ])->save();

        return $payment;
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    public function recordGatewayPayload(Payment $payment, array $payload): Payment
    {
        $payment->forceFill([
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
