<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\AppSetting;
use App\Models\Payment;
use App\Models\PromotionException;
use App\Models\WalletTransaction;
use App\Services\NetworkMonthlyEstimateService;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\ProjectAccessService;
use App\Services\UsageBalanceService;
use App\Services\VmWalletRetentionService;
use App\Services\WalletService;
use App\Services\WorkspaceProtectionSettings;
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
        $retentionNotice = app(VmWalletRetentionService::class)->customerNotice($activeProject);
        $restoreAmountToman = $request->boolean('restore') ? ($retentionNotice['top_up_toman'] ?? null) : null;
        $paymentNotice = $this->paymentNotice($request, $activeProject->owner->id, $activeProject->id);
        $journeyKey = 'wallet.top_up.'.$customer->id.'.'.$activeProject->id;
        $returnAction = $request->session()->get($journeyKey.'.return_action');
        if ($selectedTab === 'top-up' && ! $paymentNotice) {
            $capturedAction = $this->topUpReturnAction($request);
            $refererPath = parse_url($request->headers->get('referer', ''), PHP_URL_PATH);
            if ($capturedAction || ($refererPath && $refererPath !== route('customer.wallet.show', [], false))) {
                $returnAction = $capturedAction;
            }
            $request->session()->put($journeyKey.'.return_action', $returnAction);
        }
        $retryPayment = $request->has('retry') && ! empty($paymentNotice['retry_url']);
        if ($retryPayment) {
            $selectedTab = 'top-up';
            $returnAction = $paymentNotice['return_action'] ?? null;
            $request->session()->put($journeyKey.'.return_action', $returnAction);
        }
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
        $estimates = app(NetworkMonthlyEstimateService::class);
        $networkEstimate = $estimates->forOwner($activeProject->owner);
        $monthlyEstimate = $estimates->roundedTotal($walletSnapshot['monthly_cost'] + $networkEstimate['amount']);
        $effectiveBalance = $walletSnapshot['balance'];
        $suggestedTopUp = max(0, $monthlyEstimate - $effectiveBalance);
        $suggestedTopUpToman = AppSetting::currency() === 'IRR'
            ? (int) ceil($suggestedTopUp / 10)
            : $suggestedTopUp;
        $projectNetworkEstimate = $estimates->forOwner($activeProject->owner, $activeProject);
        $projectMonthlyEstimate = $estimates->roundedTotal($this->walletAlerts->projectMonthlyCost($activeProject) + $projectNetworkEstimate['amount']);
        $projectMonthlyEstimateToman = AppSetting::currency() === 'IRR'
            ? (int) ceil($projectMonthlyEstimate / 10)
            : $projectMonthlyEstimate;
        $roundedMonthlyEstimateToman = intdiv($projectMonthlyEstimateToman + 999, 1000) * 1000;
        $minimumTopUpToman = AppSetting::walletMinimumTopUpToman();
        $recommendedTopUpToman = $activeProject->virtualMachines()->notDeleted()->exists()
            && $roundedMonthlyEstimateToman >= $minimumTopUpToman
            && $roundedMonthlyEstimateToman <= 50000000
                ? $roundedMonthlyEstimateToman
                : null;
        $topUpPresets = array_values(array_unique(array_filter(
            [$minimumTopUpToman, 500000, 1000000, 2500000],
            fn (int $amount): bool => $amount >= $minimumTopUpToman,
        )));
        if ($recommendedTopUpToman !== null) {
            $topUpPresets = array_values(array_filter($topUpPresets, fn (int $amount): bool => $amount !== $recommendedTopUpToman));
            $topUpPresets = array_slice($topUpPresets, 0, 3);
            $topUpPresets[] = $recommendedTopUpToman;
        }
        $availablePaymentGateways = $this->paymentGateways->available();
        $defaultPaymentGateway = AppSetting::defaultPaymentGateway();
        if (! array_key_exists($defaultPaymentGateway, $availablePaymentGateways)) {
            $defaultPaymentGateway = array_key_first($availablePaymentGateways) ?? '';
        }

        return view('customer.wallet.show', [
            'customer' => $customer,
            'activeProject' => $activeProject,
            'activeMembership' => $this->projects->membership($activeProject, $customer),
            'projects' => $this->projects->projectsFor($customer),
            'wallet' => $wallet,
            'wallets' => $this->wallets,
            'transactions' => $transactions,
            'restoreAmountToman' => $restoreAmountToman,
            'selectedType' => $selectedType,
            'selectedTab' => $selectedTab,
            'effectiveBalance' => $effectiveBalance,
            'monthlyEstimate' => $monthlyEstimate,
            'networkEstimate' => $networkEstimate,
            'projectMonthlyEstimate' => $projectMonthlyEstimate,
            'suggestedTopUp' => $suggestedTopUp,
            'suggestedTopUpToman' => $suggestedTopUpToman,
            'recommendedTopUpToman' => $recommendedTopUpToman,
            'monthlyCredits' => (int) (clone $baseQuery)->where('amount', '>', 0)->sum('amount'),
            'monthlyCharges' => (int) abs((clone $baseQuery)->where('amount', '<', 0)->sum('amount')),
            'canTopUp' => $this->projects->canViewBilling($activeProject, $customer),
            'minimumTopUpToman' => $minimumTopUpToman,
            'topUpPresets' => $topUpPresets,
            'availablePaymentGateways' => $availablePaymentGateways,
            'defaultPaymentGateway' => $defaultPaymentGateway,
            'retryAmountToman' => $retryPayment ? $paymentNotice['retry_amount'] : null,
            'retryGateway' => $retryPayment && array_key_exists($paymentNotice['retry_gateway'], $availablePaymentGateways) ? $paymentNotice['retry_gateway'] : $defaultPaymentGateway,
            'retryCode' => $retryPayment ? $request->session()->get('wallet.retry_codes.'.$paymentNotice['payment_id'], '') : '',
            'returnAction' => $paymentNotice ? ($paymentNotice['return_action'] ?? null) : $returnAction,
            'currentBalanceRial' => AppSetting::currency() === 'IRR' ? $effectiveBalance : $effectiveBalance * 10,
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
        $shutdownAt = -max(1, (int) ceil($monthlyEstimate * app(WorkspaceProtectionSettings::class)->value($activeProject, 'wallet_shutdown_percentage') / 100));

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

    private function topUpReturnAction(Request $request): ?array
    {
        $destinations = [
            'create-server' => ['url' => route('customer.servers.create', [], false), 'label' => 'ادامه ساخت سرور'],
            'servers' => ['url' => route('customer.servers.index', [], false), 'label' => 'بازگشت به سرورها'],
            'backups' => ['url' => route('customer.backups.index', [], false), 'label' => 'بازگشت به پشتیبان‌گیری'],
            'domains' => ['url' => route('customer.domains.index', [], false), 'label' => 'بازگشت به دامنه‌ها'],
        ];
        $destination = $request->query('return_to');
        if (is_string($destination) && isset($destinations[$destination])) {
            return $destinations[$destination];
        }
        $referer = $request->headers->get('referer', '');
        if (parse_url($referer, PHP_URL_HOST) !== $request->getHost()) {
            return null;
        }
        $path = parse_url($referer, PHP_URL_PATH);
        foreach ($destinations as $action) {
            if ($action['url'] === $path) {
                return $action;
            }
        }
        if (is_string($path) && preg_match('~^/servers/[0-9a-f-]{36}(?:/(?:overview|resources|billing|upgrade|rebuild|activity))?$~i', $path)) {
            return ['url' => $path, 'label' => 'بازگشت به مدیریت سرور'];
        }

        return null;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function paymentNotice(Request $request, int $ownerCustomerId, int $projectId): ?array
    {
        $paymentId = $request->integer('payment_id') ?: $request->integer('retry');

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

        $journey = data_get($payment->gateway_payload, '_aviato_wallet_journey', []);
        if ($journey && ((int) ($journey['project_id'] ?? 0) !== $projectId || (int) ($journey['actor_id'] ?? 0) !== $request->user('customer')->id)) {
            return null;
        }
        $returnAction = $journey['return_action'] ?? null;
        if ($payment->isSuccessful()) {
            $request->session()->forget('wallet.retry_codes.'.$payment->id);
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
                'return_action' => $returnAction,
                'receipt_url' => route('customer.payments.receipt.show', $payment, false),
                'promotion_success' => $payment->promotion_bonus_amount > 0 && ! $promotionException,
            ],
            Payment::STATUS_FAILED, Payment::STATUS_CANCELLED => [
                'tone' => 'error',
                'message' => $payment->status === Payment::STATUS_CANCELLED
                    ? 'پرداخت لغو شد و مبلغی به کیف پول اضافه نشد. می‌توانید با همان مبلغ دوباره تلاش کنید.'
                    : 'پرداخت تایید نشد و مبلغی به کیف پول اضافه نشد. می‌توانید دوباره تلاش کنید یا درگاه دیگری انتخاب کنید.',
                'retry_url' => $payment->type === Payment::TYPE_TOP_UP ? route('customer.wallet.show', ['topup' => 1, 'retry' => $payment->id], false) : null,
                'retry_amount' => intdiv($payment->amount, 10),
                'retry_gateway' => $payment->provider,
                'payment_id' => $payment->id,
                'return_action' => $returnAction,
            ],
            default => [
                'return_action' => $returnAction,
                'tone' => 'pending',
                'message' => 'نتیجه پرداخت دریافت شد، اما تایید نهایی هنوز انجام نشده است. لطفا چند دقیقه دیگر وضعیت کیف پول را بررسی کنید.',
            ],
        };
    }
}
