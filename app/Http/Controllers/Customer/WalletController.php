<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Payment;
use App\Models\PromotionException;
use App\Models\WalletTransaction;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ProjectAccessService;
use App\Services\UsageBalanceService;
use App\Services\WalletService;
use App\Services\WorkspaceWalletAlertService;
use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Morilog\Jalali\Jalalian;

class WalletController extends Controller
{
    public function __construct(
        private readonly WalletService $wallets,
        private readonly ProjectAccessService $projects,
        private readonly UsageBalanceService $usageBalances,
        private readonly PaymentGatewayManager $paymentGateways,
        private readonly WorkspaceWalletAlertService $walletAlerts,
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
        $selectedTab = $request->query('tab');
        $selectedTab = in_array($selectedTab, ['overview', 'top-up', 'transactions'], true) ? $selectedTab : 'overview';
        if ($request->boolean('topup') || $request->boolean('gift_card') || $request->session()->has('errors')) {
            $selectedTab = 'top-up';
        } elseif ($selectedType !== 'all' || $request->has('page')) {
            $selectedTab = 'transactions';
        }
        $paymentNotice = $this->paymentNotice($request, $activeProject->owner->id);
        $transactions = $wallet->transactions()
            ->with('reference')
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
        $walletSnapshot = $this->walletAlerts->snapshot($activeProject->owner);
        $monthlyEstimate = $walletSnapshot['monthly_cost'];
        $effectiveBalance = $walletSnapshot['balance'];
        $suggestedTopUp = max(0, $monthlyEstimate - $effectiveBalance);
        $suggestedTopUpToman = AppSetting::currency() === 'IRR'
            ? (int) ceil($suggestedTopUp / 10)
            : $suggestedTopUp;
        $projectMonthlyEstimate = $this->walletAlerts->projectMonthlyCost($activeProject);
        $projectMonthlyEstimateToman = AppSetting::currency() === 'IRR'
            ? (int) ceil($projectMonthlyEstimate / 10)
            : $projectMonthlyEstimate;
        $recommendedTopUpToman = $activeProject->virtualMachines()->notDeleted()->exists()
            && $projectMonthlyEstimateToman >= 250000
            && $projectMonthlyEstimateToman <= 50000000
                ? $projectMonthlyEstimateToman
                : null;
        $topUpPresets = [250000, 500000, 1000000, 2500000];
        $lastPreset = $recommendedTopUpToman ?? 10000000;
        $topUpPresets = array_values(array_filter($topUpPresets, fn (int $amount): bool => $amount !== $lastPreset));
        $topUpPresets[] = $lastPreset;

        return view('customer.wallet.show', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'transactions' => $transactions,
            'selectedType' => $selectedType,
            'selectedTab' => $selectedTab,
            'effectiveBalance' => $effectiveBalance,
            'monthlyEstimate' => $monthlyEstimate,
            'projectMonthlyEstimate' => $projectMonthlyEstimate,
            'suggestedTopUp' => $suggestedTopUp,
            'suggestedTopUpToman' => $suggestedTopUpToman,
            'recommendedTopUpToman' => $recommendedTopUpToman,
            'monthlyCredits' => (int) (clone $baseQuery)->where('amount', '>', 0)->sum('amount'),
            'monthlyCharges' => (int) abs((clone $baseQuery)->where('amount', '<', 0)->sum('amount')),
            'canTopUp' => $this->projects->canViewBilling($activeProject, $customer),
            'topUpPresets' => $topUpPresets,
            'availablePaymentGateways' => $this->paymentGateways->available(),
            'defaultPaymentGateway' => AppSetting::defaultPaymentGateway(),
            'paymentNotice' => $paymentNotice,
        ]);
    }

    public function transactionsJson(Request $request): JsonResponse
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
            'from' => ['nullable', 'string', 'max:15'],
            'to' => ['nullable', 'string', 'max:15'],
            'search' => ['nullable', 'string', 'max:255'],
        ]);

        $selectedType = $filters['type'] ?? 'all';

        $parseDate = function (?string $value, ?Carbon $fallback): ?Carbon {
            if (! $value) {
                return $fallback;
            }
            try {
                return str_contains($value, '/')
                    ? Jalalian::fromFormat('Y/m/d', $value)->toCarbon()
                    : Carbon::parse($value);
            } catch (\Throwable) {
                return $fallback;
            }
        };

        $from = $parseDate($filters['from'] ?? null, null);
        $to = $parseDate($filters['to'] ?? null, null);

        $transactions = $wallet->transactions()
            ->with('reference')
            ->where(function ($query) use ($activeProject): void {
                $query->where('metadata->project_id', $activeProject->id)
                    ->orWhereNull('metadata->project_id');
            })
            ->when($selectedType !== 'all', fn ($query) => $query->where('type', $selectedType))
            ->when($from, fn ($query) => $query->whereDate('created_at', '>=', $from))
            ->when($to, fn ($query) => $query->whereDate('created_at', '<=', $to))
            ->when($filters['search'] ?? null, fn ($query) => $query->where('description', 'like', '%'.$filters['search'].'%'))
            ->orderBy('created_at', 'desc')
            ->paginate(12);

        $html = view('customer.wallet._transactions', [
            'transactions' => $transactions,
            'wallets' => $this->wallets,
        ])->render();

        return response()->json([
            'html' => $html,
            'hasPages' => $transactions->hasPages(),
        ]);
    }

    public function suspensionNotice(Request $request): View
    {
        $customer = $request->user('customer');
        $activeProject = $this->projects->activeProject($request, $customer);
        $wallet = $this->wallets->walletFor($activeProject->owner);
        $effectiveBalance = $this->usageBalances->effectiveBalance($activeProject->owner);
        $monthlyEstimate = $this->walletAlerts->snapshot($activeProject->owner)['monthly_cost'];
        $shutdownAt = -max(1, (int) ceil($monthlyEstimate * AppSetting::customerWalletShutdownPercentage() / 100));

        return view('customer.suspension.notice', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'effectiveBalance' => $effectiveBalance,
            'shutdownAt' => $shutdownAt,
        ]);
    }

    /**
     * @return array{tone: string, message: string, receipt_url?: string}|null
     */
    private function paymentNotice(Request $request, int $ownerCustomerId): ?array
    {
        $paymentId = $request->integer('payment_id');

        if ($paymentId <= 0) {
            return null;
        }

        $payment = Payment::query()
            ->whereKey($paymentId)
            ->where('customer_id', $ownerCustomerId)
            ->first();

        if (! $payment) {
            return null;
        }

        $promotionException = PromotionException::query()->where('payment_id', $payment->id)->where('status', 'open')->exists();

        return match ($payment->status) {
            Payment::STATUS_SUCCESSFUL => [
                'tone' => $promotionException ? 'pending' : 'success',
                'message' => $promotionException
                    ? 'پرداخت تایید و مبلغ اصلی به کیف پول افزوده شد؛ پاداش کارت هدیه در حال بررسی است.'
                    : ($payment->promotion_bonus_amount > 0
                        ? 'پرداخت تایید شد و مبلغ اصلی به همراه پاداش کارت هدیه به کیف پول افزوده شد.'
                        : 'پرداخت با موفقیت تایید شد و کیف پول شما شارژ شد.'),
                'receipt_url' => route('customer.payments.receipt.show', $payment, false),
                'promotion_success' => $payment->promotion_bonus_amount > 0 && ! $promotionException,
            ],
            Payment::STATUS_FAILED, Payment::STATUS_CANCELLED => [
                'tone' => 'error',
                'message' => 'پرداخت تایید نشد و مبلغی به کیف پول اضافه نشد.',
            ],
            default => [
                'tone' => 'pending',
                'message' => 'نتیجه پرداخت دریافت شد، اما تایید نهایی هنوز انجام نشده است. لطفا چند دقیقه دیگر وضعیت کیف پول را بررسی کنید.',
            ],
        };
    }
}
