<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Payment;
use App\Models\UsageSettlement;
use App\Models\Wallet;
use Carbon\CarbonInterface;
use Morilog\Jalali\Jalalian;

class AdminDashboardFinance
{
    public const PENDING_AGE_MINUTES = 15;

    public const RECONCILIATION_GRACE_MINUTES = 5;

    public function __construct(private readonly WalletService $wallets) {}

    public function snapshot(int $days): array
    {
        $now = now();
        $from = $now->copy()->subDays($days - 1)->startOfDay();
        $attempts = Payment::query()->whereBetween('created_at', [$from, $now]);
        $statusCounts = (clone $attempts)
            ->selectRaw('status, COUNT(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');
        $successful = (int) ($statusCounts[Payment::STATUS_SUCCESSFUL] ?? 0);
        $failed = (int) ($statusCounts[Payment::STATUS_FAILED] ?? 0);
        $cancelled = (int) ($statusCounts[Payment::STATUS_CANCELLED] ?? 0);
        $pending = (int) ($statusCounts[Payment::STATUS_PENDING] ?? 0);
        $finalized = $successful + $failed + $cancelled;

        $collected = $this->collectionQuery($from, $now)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->get()
            ->map(fn ($row): array => $this->money((int) $row->total, $row->currency));

        $providerAttempts = (clone $attempts)
            ->selectRaw('provider, status, COUNT(*) as total')
            ->groupBy('provider', 'status')
            ->get()
            ->groupBy('provider');
        $providerCollections = $this->collectionQuery($from, $now)
            ->selectRaw('provider, currency, SUM(amount) as total')
            ->groupBy('provider', 'currency')
            ->get()
            ->groupBy('provider');
        $agedByProvider = Payment::query()
            ->where('status', Payment::STATUS_PENDING)
            ->where('created_at', '<=', $now->copy()->subMinutes(self::PENDING_AGE_MINUTES))
            ->selectRaw('provider, COUNT(*) as total')
            ->groupBy('provider')
            ->pluck('total', 'provider');
        $providers = $providerAttempts->keys()
            ->merge($providerCollections->keys())
            ->merge($agedByProvider->keys())
            ->unique()
            ->sort()
            ->values()
            ->map(function (string $provider) use ($providerAttempts, $providerCollections, $agedByProvider): array {
                $statuses = $providerAttempts->get($provider, collect())->pluck('total', 'status');
                $success = (int) ($statuses[Payment::STATUS_SUCCESSFUL] ?? 0);
                $failed = (int) ($statuses[Payment::STATUS_FAILED] ?? 0);
                $cancelled = (int) ($statuses[Payment::STATUS_CANCELLED] ?? 0);
                $finalized = $success + $failed + $cancelled;

                return [
                    'key' => $provider,
                    'name' => $this->providerName($provider),
                    'attempts' => $finalized + (int) ($statuses[Payment::STATUS_PENDING] ?? 0),
                    'successful' => $success,
                    'failed' => $failed + $cancelled,
                    'rate' => $finalized >= 10 ? (int) round($success / $finalized * 100) : null,
                    'aged_pending' => (int) ($agedByProvider[$provider] ?? 0),
                    'collected' => $providerCollections->get($provider, collect())
                        ->map(fn ($row): array => $this->money((int) $row->total, $row->currency))
                        ->all(),
                ];
            });

        $negativeWallets = Wallet::query()->where('balance', '<', 0);
        $trendDays = max(7, $days);
        $trendFrom = $now->copy()->subDays($trendDays - 1)->startOfDay();
        $trendRows = $this->collectionQuery($trendFrom, $now)
            ->selectRaw('DATE(paid_at) as day, currency, SUM(amount) as total')
            ->groupByRaw('DATE(paid_at), currency')
            ->get();
        $preferredCurrency = AppSetting::currency();
        $trendCurrency = $trendRows->contains('currency', $preferredCurrency)
            ? $preferredCurrency
            : ($trendRows->first()?->currency ?? $preferredCurrency);
        $trendTotals = $trendRows->where('currency', $trendCurrency)->pluck('total', 'day');
        $trend = collect(range($trendDays - 1, 0))
            ->map(function (int $offset) use ($now, $trendTotals): array {
                $date = $now->copy()->subDays($offset);

                return [
                    'date' => $date->toDateString(),
                    'label' => Jalalian::fromCarbon($date)->format('m/d'),
                    'amount' => (int) ($trendTotals[$date->toDateString()] ?? 0),
                ];
            });

        return [
            'days' => $days,
            'from' => $from,
            'to' => $now,
            'collected' => $collected,
            'successful_attempts' => $successful,
            'failed_attempts' => $failed + $cancelled,
            'pending_attempts' => $pending,
            'finalized_attempts' => $finalized,
            'success_rate' => $finalized > 0 ? (int) round($successful / $finalized * 100) : null,
            'aged_pending' => (int) $agedByProvider->sum(),
            'reconciliation_count' => $this->uncreditedPayments()->count(),
            'providers' => $providers,
            'trend' => $trend,
            'trend_days' => $trendDays,
            'trend_currency' => $trendCurrency,
            'trend_currency_count' => $trendRows->pluck('currency')->unique()->count(),
            'settled_consumption' => (int) UsageSettlement::query()
                ->whereNotNull('settled_at')
                ->where('service_date', '>=', $from->toDateString())
                ->where('service_date', '<', $now->copy()->addDay()->toDateString())
                ->sum('amount'),
            'negative_wallet_count' => (clone $negativeWallets)->count(),
            'negative_wallet_total' => abs((int) (clone $negativeWallets)->sum('balance')),
            'recent' => Payment::query()->with('customer')->latest('id')->limit(8)->get(),
        ];
    }

    public function uncreditedPayments()
    {
        return Payment::query()
            ->where('status', Payment::STATUS_SUCCESSFUL)
            ->whereNotNull('paid_at')
            ->where('paid_at', '<=', now()->subMinutes(self::RECONCILIATION_GRACE_MINUTES))
            ->whereDoesntHave('walletTransactions', fn ($query) => $query->where('type', 'credit'));
    }

    private function collectionQuery(CarbonInterface $from, CarbonInterface $to)
    {
        return Payment::query()
            ->where('status', Payment::STATUS_SUCCESSFUL)
            ->whereNotNull('paid_at')
            ->whereBetween('paid_at', [$from, $to]);
    }

    private function money(int $amount, string $currency): array
    {
        return [
            'amount' => $amount,
            'currency' => $currency,
            'formatted' => $this->wallets->format($amount, $currency),
        ];
    }

    private function providerName(string $provider): string
    {
        return match ($provider) {
            'mellat' => 'ملت',
            'zibal' => 'زیبال',
            'dummy' => 'آزمایشی',
            default => $provider,
        };
    }
}
