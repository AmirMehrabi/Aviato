<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\ResellerCommission;
use App\Models\ResellerCustomer;
use App\Models\ResellerWithdrawalRequest;
use App\Models\UsageSettlement;
use App\Models\User;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ResellerService
{
    public function __construct(
        private readonly WalletService $wallets,
    ) {}

    // --- Commission Calculation ---

    public function calculateCommissionForSettlement(UsageSettlement $settlement): ?ResellerCommission
    {
        $assignment = ResellerCustomer::query()
            ->where('customer_id', $settlement->customer_id)
            ->whereNull('unassigned_at')
            ->first();

        if (! $assignment) {
            return null;
        }

        $reseller = Customer::query()->find($assignment->reseller_id);

        if (! $reseller?->isReseller() || $reseller->reseller_commission_pct <= 0) {
            return null;
        }

        if ($settlement->amount <= 0) {
            return null;
        }

        $commissionAmount = (int) floor($settlement->amount * $reseller->reseller_commission_pct / 100);

        if ($commissionAmount <= 0) {
            return null;
        }

        $payoutMethod = $reseller->reseller_payout_method;

        $commission = ResellerCommission::create([
            'reseller_id' => $reseller->id,
            'customer_id' => $settlement->customer_id,
            'usage_settlement_id' => $settlement->id,
            'service_date' => $settlement->service_date,
            'settlement_amount' => $settlement->amount,
            'commission_pct' => $reseller->reseller_commission_pct,
            'commission_amount' => $commissionAmount,
            'payout_method' => $payoutMethod,
            'status' => ResellerCommission::STATUS_PENDING,
        ]);

        if ($payoutMethod === 'auto_credit') {
            $this->creditCommission($commission->refresh());
        } else {
            $reseller->increment('reseller_earnings_balance', $commissionAmount);
        }

        return $commission;
    }

    public function creditCommission(ResellerCommission $commission): void
    {
        $reseller = Customer::query()->find($commission->reseller_id);
        $customer = Customer::query()->find($commission->customer_id);

        if (! $reseller || ! $customer) {
            return;
        }

        $transaction = $this->wallets->credit(
            $reseller,
            $commission->commission_amount,
            'کمیسیون فروشندگی - '.$customer->name.' - '.$commission->service_date->format('Y/m/d'),
            metadata: [
                'commission_id' => $commission->id,
                'type' => 'reseller_commission',
            ],
        );

        $commission->update([
            'status' => ResellerCommission::STATUS_CREDITED,
            'wallet_transaction_id' => $transaction->id,
            'credited_at' => now(),
        ]);
    }

    // --- Reseller Management ---

    public function enableReseller(Customer $customer, float $commissionPct, string $payoutMethod): void
    {
        $code = $customer->reseller_code ?: $this->generateReferralCode($customer);

        $customer->update([
            'is_reseller' => true,
            'reseller_status' => 'active',
            'reseller_commission_pct' => $commissionPct,
            'reseller_payout_method' => $payoutMethod,
            'reseller_code' => $code,
            'reseller_activated_at' => now(),
        ]);
    }

    public function disableReseller(Customer $customer): void
    {
        ResellerCustomer::query()
            ->where('reseller_id', $customer->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        $customer->update([
            'is_reseller' => false,
            'reseller_status' => null,
            'reseller_commission_pct' => null,
            'reseller_payout_method' => null,
            'reseller_code' => null,
            'reseller_activated_at' => null,
        ]);
    }

    public function suspendReseller(Customer $customer): void
    {
        $customer->update(['reseller_status' => 'suspended']);
    }

    public function activateReseller(Customer $customer): void
    {
        $customer->update(['reseller_status' => 'active']);
    }

    public function updateReseller(Customer $customer, array $data): void
    {
        $mapped = [];
        if (isset($data['commission_pct'])) {
            $mapped['reseller_commission_pct'] = $data['commission_pct'];
        }
        if (isset($data['payout_method'])) {
            $mapped['reseller_payout_method'] = $data['payout_method'];
        }
        if (isset($data['reseller_status'])) {
            $mapped['reseller_status'] = $data['reseller_status'];
        }

        $customer->update($mapped);
    }

    public function generateReferralCode(Customer $customer): string
    {
        do {
            $code = strtoupper(Str::random(8));
        } while (Customer::where('reseller_code', $code)->exists());

        return $code;
    }

    // --- Customer Assignment ---

    public function assignCustomer(Customer $reseller, Customer $customer, string $via, ?User $admin = null): ResellerCustomer
    {
        ResellerCustomer::query()
            ->where('customer_id', $customer->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);

        return ResellerCustomer::create([
            'reseller_id' => $reseller->id,
            'customer_id' => $customer->id,
            'assigned_via' => $via,
            'assigned_by_user_id' => $admin?->id,
        ]);
    }

    public function unassignCustomer(Customer $reseller, Customer $customer): void
    {
        ResellerCustomer::query()
            ->where('reseller_id', $reseller->id)
            ->where('customer_id', $customer->id)
            ->whereNull('unassigned_at')
            ->update(['unassigned_at' => now()]);
    }

    public function handleReferralRegistration(Customer $customer, string $referralCode): void
    {
        $reseller = Customer::where('reseller_code', $referralCode)
            ->where('is_reseller', true)
            ->where('reseller_status', 'active')
            ->first();

        if (! $reseller) {
            return;
        }

        $this->assignCustomer($reseller, $customer, 'referral');
    }

    // --- Withdrawal Management ---

    public function requestWithdrawal(Customer $reseller, int $amount): ResellerWithdrawalRequest
    {
        if (! $reseller->isReseller()) {
            throw ValidationException::withMessages(['reseller' => 'Customer is not a reseller.']);
        }

        if ($amount <= 0) {
            throw ValidationException::withMessages(['amount' => 'Amount must be greater than zero.']);
        }

        if ($amount > $reseller->reseller_earnings_balance) {
            throw ValidationException::withMessages(['amount' => 'Insufficient earnings balance.']);
        }

        if ($reseller->reseller_payout_method !== 'withdrawable') {
            throw ValidationException::withMessages(['payout' => 'Reseller payout method is not withdrawable.']);
        }

        return ResellerWithdrawalRequest::create([
            'reseller_id' => $reseller->id,
            'amount' => $amount,
            'status' => ResellerWithdrawalRequest::STATUS_PENDING,
        ]);
    }

    public function approveWithdrawal(ResellerWithdrawalRequest $request, User $admin, ?string $note = null): void
    {
        $reseller = Customer::query()->find($request->reseller_id);

        $reseller->decrement('reseller_earnings_balance', $request->amount);

        $request->update([
            'status' => ResellerWithdrawalRequest::STATUS_APPROVED,
            'admin_note' => $note,
            'processed_by_user_id' => $admin->id,
            'processed_at' => now(),
        ]);

        ResellerCommission::query()
            ->where('reseller_id', $request->reseller_id)
            ->where('payout_method', 'withdrawable')
            ->where('status', ResellerCommission::STATUS_PENDING)
            ->orderBy('created_at')
            ->limit($this->commissionsCountForAmount($request->reseller_id, $request->amount))
            ->update([
                'status' => ResellerCommission::STATUS_WITHDRAWN,
                'withdrawal_request_id' => $request->id,
            ]);
    }

    public function rejectWithdrawal(ResellerWithdrawalRequest $request, User $admin, ?string $note = null): void
    {
        $request->update([
            'status' => ResellerWithdrawalRequest::STATUS_REJECTED,
            'admin_note' => $note,
            'processed_by_user_id' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    public function markWithdrawalPaid(ResellerWithdrawalRequest $request, User $admin): void
    {
        $request->update([
            'status' => ResellerWithdrawalRequest::STATUS_PAID,
            'processed_by_user_id' => $admin->id,
            'processed_at' => now(),
        ]);
    }

    // --- Stats ---

    public function resellerStats(Customer $reseller): array
    {
        $activeCustomers = $reseller->activeResellerAssignments()->count();
        $totalEarned = $reseller->resellerCommissions()->sum('commission_amount');
        $pendingBalance = $reseller->reseller_earnings_balance ?? 0;

        $monthlyCommissions = $reseller->resellerCommissions()
            ->whereMonth('service_date', now()->month)
            ->whereYear('service_date', now()->year)
            ->sum('commission_amount');

        return [
            'active_customers' => $activeCustomers,
            'total_earned' => (int) $totalEarned,
            'pending_balance' => $pendingBalance,
            'monthly_commissions' => (int) $monthlyCommissions,
        ];
    }

    private function commissionsCountForAmount(int $resellerId, int $amount): int
    {
        $count = 0;
        $remaining = $amount;

        ResellerCommission::query()
            ->where('reseller_id', $resellerId)
            ->where('payout_method', 'withdrawable')
            ->where('status', ResellerCommission::STATUS_PENDING)
            ->orderBy('created_at')
            ->chunk(100, function ($commissions) use (&$count, &$remaining): void {
                foreach ($commissions as $commission) {
                    if ($remaining <= 0) {
                        return;
                    }
                    $remaining -= $commission->commission_amount;
                    $count++;
                }
            });

        return $count;
    }
}
