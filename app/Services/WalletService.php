<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\User;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WalletService
{
    public function __construct(
        private readonly CustomerWalletAlertService $walletAlerts,
        private readonly UsageBalanceService $usageBalances,
    ) {}

    public function walletFor(Customer $customer): Wallet
    {
        return $customer->wallet()->firstOrCreate([], ['balance' => 0]);
    }

    public function credit(Customer $customer, int $amount, string $description, ?User $actor = null, ?Model $reference = null, array $metadata = []): WalletTransaction
    {
        return $this->record($customer, abs($amount), WalletTransaction::TYPE_CREDIT, $description, $actor, $reference, $metadata);
    }

    public function debit(Customer $customer, int $amount, string $description, ?User $actor = null, ?Model $reference = null, array $metadata = [], bool $allowNegative = false): WalletTransaction
    {
        return $this->record($customer, -abs($amount), WalletTransaction::TYPE_DEBIT, $description, $actor, $reference, $metadata, $allowNegative);
    }

    public function charge(Customer $customer, int $amount, string $description, ?Model $reference = null, array $metadata = [], bool $allowNegative = true): WalletTransaction
    {
        return $this->record($customer, -abs($amount), WalletTransaction::TYPE_CHARGE, $description, null, $reference, $metadata, $allowNegative);
    }

    public function refund(Customer $customer, int $amount, string $description, ?User $actor = null, ?Model $reference = null, array $metadata = []): WalletTransaction
    {
        return $this->record($customer, abs($amount), WalletTransaction::TYPE_REFUND, $description, $actor, $reference, $metadata, true);
    }

    public function adjust(Customer $customer, int $signedAmount, string $description, ?User $actor = null, array $metadata = []): WalletTransaction
    {
        return $this->record($customer, $signedAmount, WalletTransaction::TYPE_ADJUSTMENT, $description, $actor, null, $metadata, true);
    }

    public function format(int $amount, ?string $currency = null): string
    {
        $currency ??= AppSetting::currency();
        $prefix = $amount < 0 ? '-' : '';

        return $prefix.$this->formattedAmount(abs($amount), $currency).' '.$this->currencyLabel($currency);
    }

    public function customerWalletNegativeThreshold(): int
    {
        return AppSetting::customerWalletNegativeThreshold();
    }

    public function isBelowNegativeThreshold(Customer $customer): bool
    {
        return $this->usageBalances->effectiveBalance($customer) < $this->customerWalletNegativeThreshold();
    }

    private function formattedAmount(int $amount, string $currency): string
    {
        if ($currency !== 'IRR') {
            return number_format($amount);
        }

        if ($amount % 10 === 0) {
            return number_format(intdiv($amount, 10));
        }

        return number_format($amount / 10, 1);
    }

    private function currencyLabel(string $currency): string
    {
        return match ($currency) {
            'IRR', 'IRT' => 'تومان',
            default => $currency,
        };
    }

    private function record(
        Customer $customer,
        int $signedAmount,
        string $type,
        string $description,
        ?User $actor = null,
        ?Model $reference = null,
        array $metadata = [],
        bool $allowNegative = false,
    ): WalletTransaction {
        if ($signedAmount === 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        $transaction = DB::transaction(function () use ($customer, $signedAmount, $type, $description, $actor, $reference, $metadata, $allowNegative): WalletTransaction {
            $wallet = Wallet::query()->where('customer_id', $customer->id)->lockForUpdate()->first();
            $wallet ??= Wallet::create(['customer_id' => $customer->id, 'balance' => 0]);

            if ($wallet->is_locked) {
                throw ValidationException::withMessages(['wallet' => $wallet->lock_reason ?: 'Wallet is locked.']);
            }

            $balanceBefore = $wallet->balance;
            $balanceAfter = $balanceBefore + $signedAmount;

            if (! $allowNegative && $balanceAfter < 0) {
                throw ValidationException::withMessages(['amount' => 'Insufficient wallet balance.']);
            }

            $transaction = WalletTransaction::create([
                'wallet_id' => $wallet->id,
                'customer_id' => $customer->id,
                'created_by_id' => $actor?->id,
                'type' => $type,
                'amount' => $signedAmount,
                'balance_before' => $balanceBefore,
                'balance_after' => $balanceAfter,
                'description' => $description,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'metadata' => $metadata,
            ]);

            $wallet->forceFill([
                'balance' => $balanceAfter,
                'last_transaction_at' => now(),
            ])->save();

            return $transaction;
        });

        $this->walletAlerts->handleWalletBalanceChange($customer);

        return $transaction;
    }
}
