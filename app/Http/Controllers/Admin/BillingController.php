<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\ResellerWithdrawalRequest;
use App\Models\UsageAccrual;
use App\Models\UsageSettlement;
use App\Models\Wallet;
use App\Models\WalletTransaction;
use App\Services\WalletService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Morilog\Jalali\Jalalian;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BillingController extends Controller
{
    public function __construct(private readonly WalletService $wallets) {}

    public function overview(Request $request)
    {
        [$from, $to] = $this->range($request);
        $payments = Payment::query()->whereBetween('created_at', [$from, $to]);
        $settlements = UsageSettlement::query()->whereBetween('service_date', [$from->toDateString(), $to->toDateString()]);

        $cash = (clone $payments)->where('status', Payment::STATUS_SUCCESSFUL)->sum('amount');
        $consumption = (clone $settlements)->sum('amount');
        $successfulPayments = (clone $payments)->where('status', Payment::STATUS_SUCCESSFUL)->count();
        $negativeWallets = Wallet::query()->where('balance', '<', 0)->count();

        $paymentDays = (clone $payments)->where('status', Payment::STATUS_SUCCESSFUL)
            ->selectRaw('DATE(paid_at) as day, SUM(amount) as total')
            ->whereNotNull('paid_at')->groupByRaw('DATE(paid_at)')->pluck('total', 'day');
        $settlementDays = (clone $settlements)->selectRaw('service_date as day, SUM(amount) as total')
            ->groupBy('service_date')->pluck('total', 'day');

        $trend = collect();
        for ($day = $from->copy()->startOfDay(); $day->lte($to); $day->addDay()) {
            $key = $day->toDateString();
            $trend->push([
                'date' => $key,
                'label' => Jalalian::fromCarbon($day)->format('m/d'),
                'cash' => (int) ($paymentDays[$key] ?? 0),
                'consumption' => (int) ($settlementDays[$key] ?? 0),
            ]);
        }

        $recent = $this->recentEvents();

        return view('admin.billing.overview', compact(
            'from', 'to', 'cash', 'consumption', 'successfulPayments', 'negativeWallets', 'trend', 'recent'
        ) + [
            'pendingPayments' => Payment::query()->where('status', Payment::STATUS_PENDING)->count(),
            'failedPayments' => Payment::query()->where('status', Payment::STATUS_FAILED)->where('created_at', '>=', now()->subDays(7))->count(),
            'walletBalance' => (int) Wallet::query()->sum('balance'),
            'resellerLiability' => (int) ResellerWithdrawalRequest::query()->whereIn('status', ['pending', 'approved'])->sum('amount'),
            'wallets' => $this->wallets,
        ]);
    }

    public function payments(Request $request)
    {
        [$from, $to] = $this->range($request);
        $query = Payment::query()->with('customer')->whereBetween('created_at', [$from, $to]);
        $this->searchCustomer($query, $request, true);
        $query->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')))
            ->when($request->filled('provider'), fn (Builder $q) => $q->where('provider', $request->string('provider')));

        return view('admin.billing.payments.index', [
            'payments' => $query->latest()->paginate(25)->withQueryString(),
            'providers' => Payment::query()->distinct()->orderBy('provider')->pluck('provider'),
            'from' => $from, 'to' => $to, 'wallets' => $this->wallets,
        ]);
    }

    public function payment(Payment $payment)
    {
        $payment->load(['customer', 'wallet']);
        $transaction = WalletTransaction::query()
            ->where('reference_type', $payment->getMorphClass())->where('reference_id', $payment->id)->first();

        return view('admin.billing.payments.show', [
            'payment' => $payment,
            'transaction' => $transaction,
            'payload' => $this->redact($payment->gateway_payload ?? []),
            'wallets' => $this->wallets,
        ]);
    }

    public function transactions(Request $request)
    {
        [$from, $to] = $this->range($request);
        $query = WalletTransaction::query()->with(['customer', 'createdBy'])->whereBetween('created_at', [$from, $to]);
        $this->searchCustomer($query, $request);
        $query->when($request->filled('type'), fn (Builder $q) => $q->where('type', $request->string('type')));

        return view('admin.billing.transactions.index', [
            'transactions' => $query->latest()->paginate(25)->withQueryString(),
            'from' => $from, 'to' => $to, 'wallets' => $this->wallets,
        ]);
    }

    public function transaction(WalletTransaction $transaction)
    {
        $transaction->load(['customer', 'wallet', 'createdBy', 'reference']);

        return view('admin.billing.transactions.show', compact('transaction') + ['wallets' => $this->wallets]);
    }

    public function invoices(Request $request)
    {
        [$from, $to] = $this->range($request);
        $query = Invoice::query()->with('customer')->withCount('items')->whereBetween('issued_at', [$from, $to]);
        $this->searchCustomer($query, $request);
        $query->when($request->filled('status'), fn (Builder $q) => $q->where('status', $request->string('status')));

        return view('admin.billing.invoices.index', [
            'invoices' => $query->latest('issued_at')->paginate(25)->withQueryString(),
            'from' => $from, 'to' => $to, 'wallets' => $this->wallets,
        ]);
    }

    public function invoice(Invoice $invoice)
    {
        $invoice->load(['customer', 'items.virtualMachine']);

        return view('admin.billing.invoices.show', compact('invoice') + ['wallets' => $this->wallets]);
    }

    public function usage(Request $request)
    {
        [$from, $to] = $this->range($request);
        $query = UsageSettlement::query()->with(['customer', 'project', 'walletTransaction'])
            ->withCount('accruals')->whereBetween('service_date', [$from->toDateString(), $to->toDateString()]);
        $this->searchCustomer($query, $request);
        $query->when($request->filled('state'), function (Builder $q) use ($request): void {
            $request->string('state')->toString() === 'settled'
                ? $q->whereNotNull('settled_at')
                : $q->whereNull('settled_at');
        });

        return view('admin.billing.usage.index', [
            'settlements' => $query->latest('service_date')->paginate(25)->withQueryString(),
            'unsettledAmount' => UsageAccrual::query()->whereNull('settled_at')->sum('amount'),
            'from' => $from, 'to' => $to, 'wallets' => $this->wallets,
        ]);
    }

    public function settlement(UsageSettlement $settlement)
    {
        $settlement->load(['customer', 'project', 'walletTransaction', 'accruals']);

        return view('admin.billing.usage.show', compact('settlement') + ['wallets' => $this->wallets]);
    }

    public function wallets(Request $request)
    {
        $query = Wallet::query()->with('customer');
        $query->when($request->filled('q'), fn (Builder $q) => $q->whereHas('customer', fn (Builder $c) => $this->customerSearch($c, $request->string('q')->toString())))
            ->when($request->string('state')->toString() === 'negative', fn (Builder $q) => $q->where('balance', '<', 0))
            ->when($request->string('state')->toString() === 'locked', fn (Builder $q) => $q->where('is_locked', true));

        return view('admin.billing.wallets.index', [
            'walletRows' => $query->orderBy('balance')->paginate(25)->withQueryString(),
            'wallets' => $this->wallets,
        ]);
    }

    public function export(Request $request, string $ledger): StreamedResponse
    {
        abort_unless(in_array($ledger, ['payments', 'transactions', 'invoices', 'usage', 'wallets'], true), 404);
        [$from, $to] = $this->range($request);

        return response()->streamDownload(function () use ($ledger, $from, $to): void {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF");
            fputcsv($out, $this->exportHeaders($ledger));
            $this->exportQuery($ledger, $from, $to)->chunkById(500, function ($rows) use ($out, $ledger): void {
                foreach ($rows as $row) {
                    fputcsv($out, $this->exportRow($ledger, $row));
                }
            });
            fclose($out);
        }, "billing-{$ledger}-{$from->toDateString()}-{$to->toDateString()}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function range(Request $request): array
    {
        $parse = function (?string $value, Carbon $fallback): Carbon {
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

        $from = $parse($request->input('from'), now()->subDays(29))->startOfDay();
        $to = $parse($request->input('to'), now())->endOfDay();
        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [$from, $to];
    }

    private function searchCustomer(Builder $query, Request $request, bool $paymentReferences = false): void
    {
        $query->when($request->filled('q'), function (Builder $q) use ($request, $paymentReferences): void {
            $term = $request->string('q')->toString();
            $q->where(function (Builder $nested) use ($term, $paymentReferences): void {
                $nested->where('id', $term);
                if ($paymentReferences) {
                    $nested->orWhere('authority', 'like', "%{$term}%")
                        ->orWhere('provider_reference', 'like', "%{$term}%");
                }
                $nested->orWhereHas('customer', fn (Builder $customer) => $this->customerSearch($customer, $term));
            });
        });
    }

    private function customerSearch(Builder $query, string $term): void
    {
        $query->where('name', 'like', "%{$term}%")->orWhere('email', 'like', "%{$term}%")
            ->orWhere('phone', 'like', "%{$term}%");
    }

    private function recentEvents(): Collection
    {
        $payments = Payment::query()->with('customer')->latest()->limit(5)->get()->map(fn ($p) => [
            'kind' => 'payment', 'id' => $p->id, 'customer' => $p->customer, 'label' => 'پرداخت درگاه',
            'reference' => $p->authority, 'amount' => $p->amount, 'status' => $p->status,
            'source' => $p->provider, 'at' => $p->created_at, 'url' => route('admin.billing.payments.show', $p),
        ]);
        $transactions = WalletTransaction::query()->with('customer')->latest()->limit(5)->get()->map(fn ($t) => [
            'kind' => 'transaction', 'id' => $t->id, 'customer' => $t->customer, 'label' => 'تراکنش کیف پول',
            'reference' => 'WLT-'.$t->id, 'amount' => $t->amount, 'status' => $t->type,
            'source' => $t->created_by_id ? 'مدیر' : 'سیستم', 'at' => $t->created_at, 'url' => route('admin.billing.transactions.show', $t),
        ]);

        return $payments->concat($transactions)->sortByDesc('at')->take(8)->values();
    }

    private function redact(array $payload): array
    {
        $sensitive = ['card', 'pan', 'token', 'password', 'secret', 'mobile', 'phone', 'email', 'national', 'merchant'];
        foreach ($payload as $key => $value) {
            if (is_array($value)) {
                $payload[$key] = $this->redact($value);
            } elseif (collect($sensitive)->contains(fn ($needle) => str_contains(strtolower((string) $key), $needle))) {
                $payload[$key] = $value === null ? null : mb_substr((string) $value, 0, 3).'******';
            }
        }

        return $payload;
    }

    private function exportQuery(string $ledger, Carbon $from, Carbon $to): Builder
    {
        return match ($ledger) {
            'payments' => Payment::query()->with('customer')->whereBetween('created_at', [$from, $to]),
            'transactions' => WalletTransaction::query()->with(['customer', 'createdBy'])->whereBetween('created_at', [$from, $to]),
            'invoices' => Invoice::query()->with('customer')->whereBetween('issued_at', [$from, $to]),
            'usage' => UsageSettlement::query()->with(['customer', 'project'])->whereBetween('service_date', [$from->toDateString(), $to->toDateString()]),
            'wallets' => Wallet::query()->with('customer'),
        };
    }

    private function exportHeaders(string $ledger): array
    {
        return match ($ledger) {
            'payments' => ['ID', 'Customer', 'Email', 'Provider', 'Status', 'Amount', 'Currency', 'Authority', 'Provider reference', 'Created at', 'Paid at'],
            'transactions' => ['ID', 'Customer', 'Type', 'Amount', 'Balance before', 'Balance after', 'Description', 'Created by', 'Created at'],
            'invoices' => ['ID', 'Number', 'Customer', 'Status', 'Period start', 'Period end', 'Subtotal', 'Tax', 'Total', 'Issued at'],
            'usage' => ['ID', 'Customer', 'Project', 'Service date', 'Amount', 'Settled at', 'Wallet transaction ID'],
            'wallets' => ['ID', 'Customer', 'Email', 'Balance', 'Locked', 'Lock reason', 'Last transaction at'],
        };
    }

    private function exportRow(string $ledger, $row): array
    {
        return match ($ledger) {
            'payments' => [$row->id, $row->customer?->name, $row->customer?->email, $row->provider, $row->status, $row->amount, $row->currency, $row->authority, $row->provider_reference, $row->created_at, $row->paid_at],
            'transactions' => [$row->id, $row->customer?->name, $row->type, $row->amount, $row->balance_before, $row->balance_after, $row->description, $row->createdBy?->name ?: 'system', $row->created_at],
            'invoices' => [$row->id, $row->number, $row->customer?->name, $row->status, $row->period_start, $row->period_end, $row->subtotal_amount, $row->tax_amount, $row->total_amount, $row->issued_at],
            'usage' => [$row->id, $row->customer?->name, $row->project?->name, $row->service_date, $row->amount, $row->settled_at, $row->wallet_transaction_id],
            'wallets' => [$row->id, $row->customer?->name, $row->customer?->email, $row->balance, $row->is_locked ? 'yes' : 'no', $row->lock_reason, $row->last_transaction_at],
        };
    }
}
