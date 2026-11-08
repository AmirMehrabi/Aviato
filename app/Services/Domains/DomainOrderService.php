<?php

namespace App\Services\Domains;

use App\Jobs\ProcessDomainOrder;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\DomainOrder;
use App\Models\ManagedDomain;
use App\Models\Payment;
use App\Models\Project;
use App\Services\Notifications\NotificationEventSubscriber;
use App\Services\PaymentService;
use App\Services\UsageBalanceService;
use App\Services\WalletService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DomainOrderService
{
    public function __construct(private readonly DomainPricingService $pricing, private readonly DomainProvider $provider, private readonly WalletService $wallets) {}

    public function create(Customer $customer, string $domain, int $period, ?ManagedDomain $owned = null, ?Project $project = null): DomainOrder
    {
        abort_if($customer->isSuspended(), 403);
        abort_unless($owned ? $owned->customer_id === $customer->id : $customer->canPurchaseDomains(), 403);
        $project = $owned?->project ?? $project ?? app(DomainWorkspaceService::class)->firstOwned($customer);
        abort_unless((int) $project->owner_customer_id === (int) $customer->id, 403);
        $operation = $owned ? 'renew' : 'register';
        abort_unless($owned || AppSetting::domainSalesEnabled(), 403);
        if ($owned && $owned->name !== $domain) {
            abort(404);
        }
        $quote = $this->pricing->quote($domain, $operation, $period, $owned?->provider);
        // Reuse an unfinished checkout, including saved contact/setup details.
        $existing = DomainOrder::where('project_id', $project->id)->where('customer_id', $customer->id)->where('domain', $domain)->where('operation', $operation)->where('period', $period)->where('provider', $quote['provider'])->whereIn('status', ['draft', 'awaiting_payment'])->latest('id')->first();
        if ($existing) {
            return $existing;
        }
        if (ManagedDomain::where('name', $domain)->exists() && ! $owned) {
            throw ValidationException::withMessages(['domain' => 'این دامنه قبلاً ثبت شده است.']);
        }

        $previous = DomainOrder::where('project_id', $project->id)->where('customer_id', $customer->id)->where('domain', $domain)->where('contact_mode', $quote['contact_mode'])->where('provider', $quote['provider'])->latest('id')->first();

        return DomainOrder::create($quote + ['uuid' => (string) Str::uuid(), 'customer_id' => $customer->id, 'project_id' => $project->id, 'domain' => $domain, 'operation' => $operation, 'period' => $period, 'managed_domain_id' => $owned?->id, 'expiry_before' => $owned?->expires_at, 'registrant' => $owned?->registrant ?? $previous?->registrant, 'nameservers' => $owned?->nameservers ?? $previous?->nameservers]);
    }

    public function refreshQuote(DomainOrder $order): void
    {
        $quote = $this->pricing->quote($order->domain, $order->operation, $order->period, $order->provider);
        DB::transaction(function () use ($order, $quote): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($order->status, ['draft', 'awaiting_payment'], true), 409);
            if ($order->contact_mode !== $quote['contact_mode']) {
                $quote['registrant'] = null;
            }
            $order->update($quote + ['expiry_before' => $order->operation === 'renew' ? $order->managedDomain?->expires_at : null, 'consented_at' => null, 'status' => 'draft', 'customer_message' => null]);
        });
    }

    public function available(Customer $customer): int
    {
        return max(0, app(UsageBalanceService::class)->effectiveBalance($customer));
    }

    public function assertPurchasable(DomainOrder $order): void
    {
        abort_if($order->customer->isSuspended(), 403);
        abort_unless($order->operation === 'renew' || ($order->customer->canPurchaseDomains() && AppSetting::domainSalesEnabled()), 403);
        if ($order->currency !== AppSetting::currency() || $order->quoted_until->isPast()) {
            throw ValidationException::withMessages(['payment' => 'زمان اعتبار قیمت تمام شده است. قیمت را به‌روز و دوباره تأیید کنید.']);
        }
        $this->assertDomainState($order);
        if (! $order->registrant || ! $order->nameservers || ! $order->consented_at) {
            throw ValidationException::withMessages(['payment' => 'اطلاعات مالک، نیم‌سرورها و تأیید سفارش را کامل کنید.']);
        }
    }

    private function assertDomainState(DomainOrder $order): void
    {
        if ($order->operation === 'register' && ManagedDomain::where('name', $order->domain)->exists()) {
            throw ValidationException::withMessages(['payment' => 'این دامنه قبلاً ثبت شده است. سفارش‌های خود را بررسی کنید.']);
        }
        if ($order->operation === 'renew' && (! $order->managedDomain || $order->managedDomain->project_id !== $order->project_id || ! $order->expiry_before || ! $order->managedDomain->expires_at?->equalTo($order->expiry_before))) {
            throw ValidationException::withMessages(['payment' => 'تاریخ انقضای دامنه تغییر کرده است. از صفحه دامنه سفارش تمدید تازه بسازید.']);
        }
    }

    public function assertPurchasableForFulfillment(DomainOrder $order): void
    {
        $this->assertDomainState($order);
        abort_if($order->customer->isSuspended(), 403);
        abort_unless($order->operation === 'renew' || ($order->customer->canPurchaseDomains() && AppSetting::domainSalesEnabled()), 403);
        if ($order->currency !== AppSetting::currency()) {
            throw ValidationException::withMessages(['payment' => 'واحد پول تغییر کرده است. مبلغ به کیف پول بازگشت.']);
        }
    }

    public function validateSupplier(DomainOrder $order): void
    {
        $quote = $this->pricing->quote($order->domain, $order->operation, $order->period, $order->provider);
        if ($quote['total'] !== $order->total || $quote['currency'] !== $order->currency) {
            throw ValidationException::withMessages(['payment' => 'قیمت فروش تغییر کرده است. قیمت جدید را بررسی و تأیید کنید.']);
        }
        if ($order->provider === 'irpower') {
            if ($quote['supplier_price'] > $order->supplier_price) {
                throw ValidationException::withMessages(['payment' => 'هزینه سرویس‌دهنده تغییر کرده است. قیمت سفارش را به‌روز کنید.']);
            }
            app(IrpowerProvider::class)->nameserverPayload($order->nameservers ?? []);
            if ($order->operation === 'register') {
                $check = app(DomainAvailabilityService::class)->check($order->domain, false);
                if (! $check['available'] || $check['premium'] !== false) {
                    throw ValidationException::withMessages(['payment' => 'آزاد بودن یا قیمت عادی این دامنه قابل تأیید نیست. نام دیگری انتخاب کنید یا با پشتیبانی تماس بگیرید.']);
                }
                if ($order->contact_mode === 'registry_handle') {
                    app(IrpowerProvider::class)->validateHandles($order->registrant ?? []);
                }
            } else {
                app(IrpowerProvider::class)->nameservers($order->domain);
            }

            return;
        }
        $check = $this->provider->check($order->domain, $order->period, $order->operation);
        $price = $check['price'] ?? null;
        // A live domain-specific cost is mandatory; never guess for premium or missing prices.
        if (($check['premium'] ?? false) !== false || ! is_numeric($price) || $price <= 0 || $price != (int) $price) {
            throw ValidationException::withMessages(['payment' => 'قیمت قطعی این دامنه قابل تأیید نیست. لطفاً با پشتیبانی تماس بگیرید.']);
        }
        if ($order->operation === 'register' && ! $check['available']) {
            throw ValidationException::withMessages(['payment' => 'این دامنه دیگر برای ثبت آزاد نیست. لطفاً نام دیگری انتخاب کنید.']);
        }
        if ((int) $price > $order->supplier_price) {
            throw ValidationException::withMessages(['payment' => 'هزینه سرویس‌دهنده تغییر کرده است. پس از به‌روزرسانی قیمت‌ها دوباره تلاش کنید.']);
        }
    }

    public function pay(DomainOrder $order, ?string $gateway, bool $walletOnly = false, ?string $paymentMethod = null): ?Payment
    {
        if (! in_array($order->status, ['draft', 'awaiting_payment'], true)) {
            return null;
        }
        if ($order->payment?->isPending() && ! $walletOnly) {
            return $order->payment;
        }
        $this->assertPurchasable($order);
        $this->validateSupplier($order);

        return DB::transaction(function () use ($order, $gateway, $walletOnly, $paymentMethod): ?Payment {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            if (! in_array($order->status, ['draft', 'awaiting_payment'], true)) {
                return null;
            }
            if ($order->payment?->isPending()) {
                if ($walletOnly) {
                    throw ValidationException::withMessages(['payment' => 'یک پرداخت برای این سفارش در جریان است.']);
                }

                return $order->payment;
            }
            $this->assertPurchasable($order);
            if (DB::table('domain_claims')->where('domain', $order->domain)->where('domain_order_id', '!=', $order->id)->exists()) {
                $order->update(['customer_message' => 'سفارش دیگری برای این دامنه در حال انجام است. مبلغ در کیف پول شما محفوظ است.']);

                return null;
            }
            if ($paymentMethod === 'online') {
                $payment = app(PaymentService::class)->createDomainPayment($order, (string) $gateway);
                $order->update(['payment_id' => $payment->id, 'status' => 'awaiting_payment', 'customer_message' => null]);

                return $payment;
            }
            // Serialize with all other wallet charges before calculating the shortfall.
            $wallet = $this->wallets->walletFor($order->customer);
            $wallet = $wallet->newQuery()->lockForUpdate()->findOrFail($wallet->id);
            if ($wallet->is_locked) {
                throw ValidationException::withMessages(['payment' => 'کیف پول حساب مسدود است. با پشتیبانی تماس بگیرید.']);
            }
            $shortfall = max(0, $order->total - $this->available($order->customer));
            if ($shortfall === 0) {
                $this->reserve($order);

                return null;
            }
            if ($walletOnly || $paymentMethod === 'wallet') {
                throw ValidationException::withMessages(['payment' => 'موجودی کیف پول کافی نیست. پرداخت آنلاین را انتخاب کنید یا کیف پول را شارژ کنید.']);
            }
            // Ordinary top-up limits remain untouched. Domain checkout funds the exact shortfall.
            $payment = app(PaymentService::class)->createTopUp($order->customer, $shortfall, 'تکمیل موجودی سفارش دامنه '.$order->domain, $gateway, domainOrder: $order);
            $order->update(['payment_id' => $payment->id, 'status' => 'awaiting_payment']);

            return $payment;
        });
    }

    public function paymentCompleted(Payment $payment): void
    {
        if (! $payment->domain_order_id) {
            return;
        }
        $order = DomainOrder::lockForUpdate()->findOrFail($payment->domain_order_id);
        if (! in_array($order->status, ['draft', 'awaiting_payment'], true)) {
            return;
        }
        try {
            $this->assertDomainState($order);
            $stateValid = true;
        } catch (ValidationException) {
            $stateValid = false;
        }
        if (! $stateValid || $order->quoted_until->isPast() || ! $order->consented_at || $order->customer->isSuspended()
            || ($order->operation === 'register' && (! $order->customer->canPurchaseDomains() || ! AppSetting::domainSalesEnabled()))
            || $order->currency !== AppSetting::currency()
            || $this->available($order->customer) < $order->total
            || $this->wallets->walletFor($order->customer)->is_locked) {
            $order->update(['status' => 'draft', 'customer_message' => 'پرداخت به کیف پول شما اضافه شد. قیمت و موجودی را بررسی و سفارش را دوباره تأیید کنید.']);

            return;
        }
        $this->reserve($order);
    }

    public function directPaymentCompleted(Payment $payment): void
    {
        $order = DomainOrder::lockForUpdate()->findOrFail($payment->domain_order_id);
        $valid = in_array($order->status, ['draft', 'awaiting_payment'], true)
            && $payment->currency === 'IRR'
            && (int) $order->payment_id === (int) $payment->id
            && $payment->amount === $order->total * ($order->currency === 'IRT' ? 10 : 1);
        try {
            $this->assertPurchasable($order);
        } catch (ValidationException|HttpException) {
            $valid = false;
        }
        if ($valid && $this->reserve($order, direct: true)) {
            return;
        }

        // A late payment never restarts a cancelled, repriced or already funded order.
        $amount = AppSetting::currency() === 'IRT' ? intdiv($payment->amount, 10) : $payment->amount;
        $refund = $this->wallets->refund($order->customer, $amount, 'بازگشت پرداخت سفارش دامنه '.$order->domain, reference: $payment,
            metadata: ['category' => 'domain_payment_returned', 'payment_id' => $payment->id, 'domain_order_id' => $order->id]);
        $payment->update(['gateway_payload' => array_merge($payment->gateway_payload ?? [], ['domain_refund_transaction_id' => $refund->id])]);
        if (in_array($order->status, ['draft', 'awaiting_payment', 'cancelled'], true)) {
            $order->update(['status' => $order->status === 'cancelled' ? 'cancelled' : 'draft', 'customer_message' => 'پرداخت دریافت شد، اما سفارش قابل انجام نبود. مبلغ پرداخت به کیف پول شما بازگشت و به حساب بانکی واریز نشده است.']);
        }
    }

    private function reserve(DomainOrder $order, bool $direct = false): bool
    {
        if ($order->funded_at) {
            return true;
        }
        if (DB::table('domain_claims')->where('domain', $order->domain)->exists()) {
            $order->update(['customer_message' => 'سفارش دیگری برای این دامنه در حال انجام است. مبلغ در کیف پول شما محفوظ است.']);

            return false;
        }
        $claimed = DB::table('domain_claims')->insertOrIgnore(['domain' => $order->domain, 'domain_order_id' => $order->id]);
        if (! $claimed) {
            return false;
        }
        $transaction = $direct ? null : $this->wallets->debit($order->customer, $order->total, 'رزرو مبلغ سفارش دامنه '.$order->domain, reference: $order, metadata: ['category' => 'domain_reservation', 'provider' => $order->provider, 'domain_order_id' => $order->id]);
        $order->update(['wallet_transaction_id' => $transaction?->id, 'funded_at' => now(), 'status' => 'funded', 'customer_message' => null]);
        DB::afterCommit(function () use ($order): void {
            try {
                ProcessDomainOrder::dispatch($order->id);
            } catch (\Throwable) {
                Log::warning('Funded domain order awaiting queue recovery.', ['order_id' => $order->id]);
            }
        });

        return true;
    }

    public function fail(DomainOrder $order, string $message): void
    {
        DB::transaction(function () use ($order, $message): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            if (in_array($order->status, ['completed', 'failed', 'cancelled'], true)) {
                return;
            }
            if ($order->funded_at && ! $order->refund_transaction_id) {
                $refund = $this->wallets->refund($order->customer, $order->total, 'بازگشت مبلغ رزرو دامنه '.$order->domain, reference: $order, metadata: ['category' => $order->wallet_transaction_id ? 'domain_reservation_released' : 'domain_payment_returned', 'provider' => $order->provider, 'payment_id' => $order->payment_id, 'original_transaction_id' => $order->wallet_transaction_id]);
                $order->refund_transaction_id = $refund->id;
            }
            $order->status = 'failed';
            $order->customer_message = $message;
            $order->save();
            DB::table('domain_claims')->where('domain_order_id', $order->id)->delete();
            DB::afterCommit(fn () => app(NotificationEventSubscriber::class)->notify($order->customer, 'domain_order', ['body' => 'سفارش دامنه '.$order->domain.' انجام نشد. مبلغ رزرو به کیف پول شما بازگشت.'], route('customer.domains.orders.show', $order)));
        });
    }
}
