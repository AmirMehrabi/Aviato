<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\WalletTransaction;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ProjectAccessService;
use App\Services\UsageBillingService;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
        private readonly UsageBillingService $usageBilling,
        private readonly PaymentGatewayManager $paymentGateways,
    ) {}

    public function show(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        abort_unless($this->projects->canViewBilling($activeProject, $customer), 404);
        $wallet = $this->wallets->walletFor($activeProject->owner);
        $filters = $request->validate([
            'type' => ['nullable', Rule::in([
                'all',
                WalletTransaction::TYPE_CREDIT,
                WalletTransaction::TYPE_CHARGE,
                WalletTransaction::TYPE_REFUND,
                WalletTransaction::TYPE_ADJUSTMENT,
                WalletTransaction::TYPE_DEBIT,
            ])],
        ]);
        $selectedType = $filters['type'] ?? 'all';
        $transactions = $wallet->transactions()
            ->where(function ($query) use ($activeProject): void {
                $query->where('metadata->project_id', $activeProject->id)
                    ->orWhereNull('metadata->project_id');
            })
            ->when($selectedType !== 'all', fn ($query) => $query->where('type', $selectedType))
            ->paginate(12)
            ->withQueryString();

        $monthStart = now()->startOfMonth();
        $baseQuery = $wallet->transactions()
            ->where(function ($query) use ($activeProject): void {
                $query->where('metadata->project_id', $activeProject->id)
                    ->orWhereNull('metadata->project_id');
            })
            ->where('created_at', '>=', $monthStart);

        return view('customer.wallet.show', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'transactions' => $transactions,
            'selectedType' => $selectedType,
            'pendingUsage' => $this->usageBilling->projectPendingUsage($activeProject->id),
            'monthlyCredits' => (int) (clone $baseQuery)->where('amount', '>', 0)->sum('amount'),
            'monthlyCharges' => (int) abs((clone $baseQuery)->where('amount', '<', 0)->sum('amount')),
            'topUpPresets' => [1000000, 3000000, 10000000, 25000000],
            'availablePaymentGateways' => $this->paymentGateways->available(),
            'defaultPaymentGateway' => AppSetting::defaultPaymentGateway(),
        ]);
    }

    public function suspensionNotice(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        $wallet = $this->wallets->walletFor($activeProject->owner);
        $pendingUsage = $this->usageBilling->projectPendingUsage($activeProject->id);

        return view('customer.suspension.notice', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'pendingUsage' => $pendingUsage,
        ]);
    }
}
