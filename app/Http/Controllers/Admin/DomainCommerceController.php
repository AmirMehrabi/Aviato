<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\ProcessDomainOrder;
use App\Models\DomainOperation;
use App\Models\DomainOrder;
use App\Models\DomainProduct;
use App\Services\Domains\DomainOrderService;
use App\Services\Domains\DomainPricingService;
use App\Services\Domains\DomainProvider;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class DomainCommerceController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate(['extension' => ['nullable', 'string', 'max:80'], 'status' => ['nullable', 'in:needs_review,confirming,funded,processing,failed,completed,draft,awaiting_payment,cancelled']]);

        return view('admin.settings.domain-commerce', [
            'products' => DomainProduct::when($filters['extension'] ?? null, fn ($query, $extension) => $query->where('extension', ltrim($extension, '.')))->orderBy('extension')->orderBy('operation')->orderBy('period')->paginate(24, ['*'], 'prices_page')->withQueryString(),
            'orders' => DomainOrder::with(['customer', 'operations'])->when($filters['status'] ?? null, fn ($query, $status) => $query->where('status', $status))->latest()->paginate(15, ['*'], 'orders_page')->withQueryString(),
            'uncertainOperations' => DomainOperation::with('domain')->whereNull('domain_order_id')->whereIn('status', ['submitting', 'uncertain'])->latest()->limit(30)->get(),
            'wallets' => app(WalletService::class),
            'reviewCount' => DomainOrder::where('status', 'needs_review')->orWhere(fn ($query) => $query->whereIn('status', ['confirming', 'processing'])->where('funded_at', '<', now()->subHour()))->count(),
        ]);
    }

    public function sync(DomainPricingService $pricing): RedirectResponse
    {
        try {
            $count = $pricing->sync();
        } catch (Throwable) {
            return back()->withErrors(['prices' => 'دریافت قیمت‌ها انجام نشد. اتصال و اعتبار حساب سرویس‌دهنده را بررسی کنید. قیمت‌های قبلی حفظ شده‌اند.']);
        }

        return back()->with('status', $count.' قیمت به‌روز شد. پسوندهای تازه تا تأیید شما غیرفعال می‌مانند.');
    }

    public function product(Request $request, DomainProduct $product): RedirectResponse
    {
        $data = $request->validate(['enabled' => ['required', 'boolean'], 'retail_toman' => ['nullable', 'integer', 'min:1', 'max:50000000'], 'minimum_margin_toman' => ['required', 'integer', 'min:0', 'max:50000000'], 'markup_percent' => ['required', 'integer', 'between:0,500'], 'automatic_pricing' => ['required', 'boolean'], 'contact_mode' => ['required', 'in:contact,registry_handle'], 'registry_verified' => ['exclude_unless:enabled,1', 'accepted_if:contact_mode,registry_handle']]);
        $retail = isset($data['retail_toman']) ? (int) $data['retail_toman'] * 10 : null;
        $minimum = (int) $data['minimum_margin_toman'] * 10;
        if ($data['automatic_pricing'] && $product->supplier_price) {
            $retail = (int) (ceil(max($product->supplier_price * (100 + $data['markup_percent']) / 100, $product->supplier_price + $minimum) / 10000) * 10000);
        }
        if ($data['enabled'] && (! in_array($product->operation, ['register', 'renew'], true) || ! $retail || ! $product->supplier_price || $retail < $product->supplier_price + $minimum || ! $product->synced_at || $product->synced_at->lt(now()->subDay()))) {
            throw ValidationException::withMessages(['retail_toman' => 'برای فعال‌سازی، قیمت معتبر و به‌روز با حداقل حاشیه سود لازم است. فعلاً فقط ثبت و تمدید قابل فروش‌اند.']);
        }
        $product->update(['enabled' => (bool) $data['enabled'], 'retail_price' => $retail, 'minimum_margin' => $minimum, 'markup_percent' => $data['markup_percent'], 'automatic_pricing' => (bool) $data['automatic_pricing'], 'contact_mode' => $data['contact_mode']]);

        return back()->with('status', 'قیمت و شرایط فروش ذخیره شد.');
    }

    public function reconcile(Request $request, DomainOrder $order, DomainProvider $provider): RedirectResponse
    {
        $data = $request->validate(['operation_id' => ['required', 'integer'], 'track_id' => ['required', 'string', 'regex:/^[0-9]{1,30}$/']]);
        $operation = $order->operations()->findOrFail($data['operation_id']);
        try {
            $result = $provider->inquiry($data['track_id']);
        } catch (Throwable) {
            return back()->withErrors(['track_id' => 'نتیجه شناسه پیگیری قابل دریافت نیست.']);
        }
        $job = match ($operation->kind) {
            'contact' => 'CreateContactJob', 'register' => 'RegisterJob', 'renew' => 'RenewJob', default => ''
        };
        $expected = $operation->kind === 'contact' ? $order->registrant['email'] : $order->domain;
        if (($result['item'] ?? null) !== $expected || ($result['job'] ?? null) !== $job) {
            throw ValidationException::withMessages(['track_id' => 'این شناسه پیگیری متعلق به دامنه، مالک یا عملیات این سفارش نیست.']);
        }
        DB::transaction(function () use ($order, $operation, $data): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($order->status, ['needs_review', 'confirming', 'processing'], true), 409);
            $operation->update(['track_id' => $data['track_id'], 'status' => 'pending']);
            $order->update(['status' => 'confirming']);
            ProcessDomainOrder::dispatch($order->id)->afterCommit();
        });

        return back()->with('status', 'پیگیری به سفارش متصل شد. نتیجه بدون ارسال دوباره درخواست بررسی می‌شود.');
    }

    public function reconcileNameservers(Request $request, DomainOperation $operation, DomainProvider $provider): RedirectResponse
    {
        abort_unless($operation->kind === 'nameservers' && $operation->managed_domain_id, 404);
        $data = $request->validate(['track_id' => ['required', 'string', 'regex:/^[0-9]{1,30}$/']]);
        try {
            $result = $provider->inquiry($data['track_id']);
        } catch (Throwable) {
            return back()->withErrors(['track_id' => 'نتیجه قابل دریافت نیست.']);
        }
        abort_unless(($result['item'] ?? '') === $operation->domain->name && ($result['job'] ?? '') === 'UpdateDnsJob', 422);
        $operation->update(['track_id' => $data['track_id'], 'status' => 'pending']);

        return back()->with('status', 'عملیات برای پیگیری آماده شد.');
    }

    public function recheck(DomainOrder $order): RedirectResponse
    {
        DB::transaction(function () use ($order): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === 'needs_review', 409);
            abort_if($order->operations()->whereIn('status', ['uncertain', 'submitting'])->exists(), 409, 'ابتدا شناسه پیگیری عملیات نامشخص را متصل کنید.');
            $order->update(['status' => 'confirming']);
            ProcessDomainOrder::dispatch($order->id)->afterCommit();
        });

        return back()->with('status', 'نتیجه عملیات قبلی دوباره بررسی می‌شود.');
    }

    public function resolveNotSubmitted(Request $request, DomainOrder $order, DomainOrderService $orders): RedirectResponse
    {
        $data = $request->validate(['provider_reference' => ['required', 'string', 'min:10', 'max:500'], 'confirmed_not_executed' => ['accepted']]);
        DB::transaction(function () use ($order, $orders, $data): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless($order->status === 'needs_review', 409);
            // Recorded operator evidence is required when the provider cannot supply a tracking id.
            $uncertain = $order->operations()->whereIn('status', ['uncertain', 'submitting'])->whereNull('track_id')->get();
            abort_if($uncertain->isEmpty(), 409);
            $order->operations()->create(['kind' => 'operator_resolution', 'status' => 'not_executed', 'payload' => ['provider_reference' => $data['provider_reference'], 'actor_id' => auth('admin')->id()]]);
            foreach ($uncertain as $operation) {
                $operation->update(['status' => 'not_executed']);
            }
            $orders->fail($order, 'انجام نشدن سفارش با سرویس‌دهنده تأیید شد. مبلغ رزرو به کیف پول شما بازگشت.');
        });

        return back()->with('status', 'عدم انجام سفارش ثبت و مبلغ رزرو به کیف پول بازگردانده شد.');
    }

    public function export(): StreamedResponse
    {
        return response()->streamDownload(function (): void {
            $stream = fopen('php://output', 'w');
            fputcsv($stream, ['order', 'customer_id', 'domain', 'operation', 'period', 'status', 'currency', 'subtotal', 'tax', 'total', 'supplier_cost_IRR', 'reservation_transaction', 'release_transaction', 'invoice', 'completed_at'], escape: '');
            DomainOrder::orderBy('id')->chunkById(100, function ($orders) use ($stream): void {
                foreach ($orders as $order) {
                    fputcsv($stream, [$order->uuid, $order->customer_id, $order->domain, $order->operation, $order->period, $order->status, $order->currency, $order->subtotal, $order->tax_amount, $order->total, $order->supplier_price, $order->wallet_transaction_id, $order->refund_transaction_id, $order->invoice_id, $order->completed_at], escape: '');
                }
            });
            fclose($stream);
        }, 'domain-accounting.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'no-store']);
    }
}
