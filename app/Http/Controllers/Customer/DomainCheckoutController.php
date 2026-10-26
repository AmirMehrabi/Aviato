<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Http\Requests\Customer\CheckDomainRequest;
use App\Models\AppSetting;
use App\Models\DomainContact;
use App\Models\DomainOrder;
use App\Models\ManagedDomain;
use App\Services\Domains\DomainOrderService;
use App\Services\Domains\DomainPricingService;
use App\Services\Domains\DomainRegistrant;
use App\Services\Domains\IrpowerProvider;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use App\Services\WalletService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class DomainCheckoutController extends Controller
{
    public function store(CheckDomainRequest $request, DomainOrderService $orders): RedirectResponse
    {
        $request->validate(['period' => ['required', 'integer', 'between:1,10']]);
        abort_unless(AppSetting::domainSalesEnabled(), 403);
        $order = $orders->create($request->user('customer'), $request->validated('domain'), (int) $request->input('period'));

        return $this->redirectTo('customer.domains.orders.show', $order);
    }

    public function show(Request $request, DomainOrder $order, DomainOrderService $orders, DomainPricingService $pricing, PaymentGatewayManager $gateways): View|JsonResponse
    {
        $this->own($request, $order);
        if ($request->expectsJson()) {
            return response()->json(['status' => $order->status, 'label' => $order->label(), 'message' => $order->customer_message]);
        }
        $step = $request->query('step', 'owner');
        if (! in_array($step, ['owner', 'setup', 'review'], true)) {
            $step = 'owner';
        }
        if ($order->operation === 'renew') {
            $step = 'review';
        }
        if ($step !== 'owner' && ! $order->registrant) {
            $step = 'owner';
        }
        if ($step === 'review' && ! $order->nameservers) {
            $step = 'setup';
        }
        $contacts = DomainContact::where('customer_id', $order->customer_id)->latest()->limit(20)->get()->filter(fn ($contact) => $order->contact_mode === 'registry_handle' ? isset($contact->details['holder']) : isset($contact->details['name']));
        $available = $orders->available($order->customer);

        return view('customer.domains.checkout', [
            'customer' => $request->user('customer'), 'order' => $order->load(['product', 'payment', 'managedDomain']), 'wallets' => app(WalletService::class), 'step' => $step,
            'contacts' => $contacts, 'countries' => DomainRegistrant::countries(), 'available' => $available, 'shortfall' => max(0, $order->total - $available), 'gateways' => $gateways->available(),
            'renewalPrice' => $pricing->renewalPrice($order->domain, $order->provider),
        ]);
    }

    public function save(Request $request, DomainOrder $order): RedirectResponse
    {
        $this->own($request, $order);
        $request->validate(['stage' => ['required', 'in:owner,setup'], 'contact_id' => ['nullable', 'integer']]);
        $stage = $request->input('stage');
        $attributes = [];
        if ($stage === 'owner') {
            if ($request->filled('contact_id')) {
                $contact = DomainContact::where('customer_id', $order->customer_id)->findOrFail($request->input('contact_id'));
                $request->merge($contact->details);
            }
            if ($order->contact_mode === 'contact') {
                $country = $request->input('country', '');
                if (is_string($country)) {
                    $country = strtoupper($country);
                    $request->merge(['country' => $country]);
                    if (is_string($request->input('phone'))) {
                        $request->merge(['phone' => DomainRegistrant::phone($request->input('phone'), $country)]);
                    }
                }
            }
            if ($order->contact_mode === 'registry_handle') {
                if ($order->provider === 'irpower' && ! isset($contact) && $request->boolean('use_holder_for_all', ! $request->has('admin'))) {
                    $request->merge(array_fill_keys(['admin', 'tech', 'billing'], $request->input('holder')));
                }
                $rules = array_fill_keys(['holder', 'admin', 'tech', 'billing'], ['required', 'string', 'max:150', 'regex:/^[a-zA-Z0-9@._-]+$/']);
            } else {
                $rules = [
                    'name' => ['required', 'string', 'max:150'], 'email' => ['required', 'email', 'max:150'], 'country' => ['required', 'string', Rule::in(array_keys(DomainRegistrant::countries()))],
                    'state' => ['required', 'string', 'max:150'], 'city' => ['required', 'string', 'max:150'], 'address' => ['required', 'string', 'max:500'],
                    'postCode' => ['required', 'string', 'max:30'], 'phone' => ['required', 'string', 'regex:/^\+[0-9]{1,3}\.[0-9]{4,14}$/'],
                    'organization' => ['nullable', 'string', 'max:150'],
                ];
            }
            $details = $request->validate($rules, ['required' => 'تکمیل :attribute لازم است.', 'email' => 'ایمیل معتبر وارد کنید.', 'phone.regex' => 'شماره تماس معتبر وارد کنید؛ مانند 09121234567 برای ایران یا ‎+98.9121234567.'], ['name' => 'نام مالک', 'email' => 'ایمیل مالک', 'country' => 'کشور', 'state' => 'استان', 'city' => 'شهر', 'address' => 'نشانی', 'postCode' => 'کد پستی', 'phone' => 'شماره تماس', 'holder' => 'شناسه صاحب امتیاز', 'admin' => 'شناسه رابط اداری', 'tech' => 'شناسه رابط فنی', 'billing' => 'شناسه رابط مالی']);
            if ($order->provider === 'irpower' && $order->contact_mode === 'registry_handle') {
                try {
                    app(IrpowerProvider::class)->validateHandles($details);
                } catch (RuntimeException $exception) {
                    throw ValidationException::withMessages(['holder' => 'ارتباط با سامانه تأیید شناسه برقرار نشد. مشخصات را نگه دارید و کمی بعد دوباره ادامه دهید.']);
                }
            }
            if ($order->provider === 'irpower' && $order->contact_mode === 'contact' && count(preg_split('/\s+/u', trim($details['name']))) < 2) {
                throw ValidationException::withMessages(['name' => 'نام و نام خانوادگی کامل مالک را وارد کنید.']);
            }
            if ($order->contact_mode === 'contact') {
                $details['fax'] = $details['phone'];
                $details['organization'] = ($details['organization'] ?? '') ?: $details['name'];
            }
            if (isset($contact) && $order->provider === 'reseller_world' && $order->contact_mode === 'contact' && ! empty($contact->details['provider_handles'][$order->product->extension])) {
                $details['provider_handle'] = $contact->details['provider_handles'][$order->product->extension];
            }
            $attributes['registrant'] = $details;
        } else {
            $data = $request->validate(['nameservers' => ['required', 'array', 'list', 'min:2', 'max:'.($order->provider === 'irpower' ? 5 : 6)], 'nameservers.*' => ['required', 'string', 'max:253', 'distinct:ignore_case', 'regex:/\A(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}\z/i']]);
            $attributes['nameservers'] = array_map('strtolower', $data['nameservers']);
        }
        DB::transaction(function () use ($order, $attributes, $request, $stage): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($order->status, ['draft', 'awaiting_payment'], true), 409);
            abort_if($order->payment?->isPending(), 409, 'ابتدا نتیجه پرداخت جاری را بررسی کنید.');
            $order->update($attributes + ['consented_at' => null]);
            if ($stage === 'owner' && $request->boolean('save_contact') && ! $request->filled('contact_id')) {
                DomainContact::create(['customer_id' => $order->customer_id, 'label' => $attributes['registrant']['name'] ?? $attributes['registrant']['holder'], 'details' => $attributes['registrant']]);
            }
        });

        return $this->redirectTo('customer.domains.orders.show', ['order' => $order, 'step' => $stage === 'owner' ? 'setup' : 'review']);
    }

    public function pay(Request $request, DomainOrder $order, DomainOrderService $orders, PaymentGatewayManager $gateways): RedirectResponse
    {
        $this->own($request, $order);
        $data = $request->validate(['terms' => ['accepted'], 'gateway' => ['nullable', Rule::in(array_keys($gateways->available()))]]);
        DB::transaction(function () use ($order): void {
            $locked = DomainOrder::lockForUpdate()->findOrFail($order->id);
            if (in_array($locked->status, ['draft', 'awaiting_payment'], true)) {
                $locked->update(['consented_at' => now()]);
            }
        });
        try {
            $payment = $orders->pay($order->fresh(), $data['gateway'] ?? null);
        } catch (RuntimeException|PaymentGatewayException) {
            return $this->redirectTo('customer.domains.orders.show', ['order' => $order, 'step' => 'review'])->withErrors(['payment' => 'ارتباط با سرویس پرداخت یا دامنه برقرار نشد. سفارش محفوظ است؛ کمی بعد دوباره تلاش کنید.']);
        }

        return $payment
            ? $this->redirectTo('customer.wallet.payments.gateway.show', $payment)
            : $this->redirectTo('customer.domains.orders.show', $order);
    }

    public function refresh(Request $request, DomainOrder $order, DomainOrderService $orders): RedirectResponse
    {
        $this->own($request, $order);
        $orders->refreshQuote($order);

        return $this->redirectTo('customer.domains.orders.show', ['order' => $order, 'step' => 'review'])->with('status', 'قیمت به‌روز شد. لطفاً مبلغ جدید را بررسی کنید.');
    }

    public function cancel(Request $request, DomainOrder $order): RedirectResponse
    {
        $this->own($request, $order);
        DB::transaction(function () use ($order): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            abort_unless(in_array($order->status, ['draft', 'awaiting_payment'], true), 409);
            // A late successful callback still credits the wallet, but never places a cancelled order.
            $order->update(['status' => 'cancelled']);
        });

        return $this->redirectTo('customer.domains.orders.show', $order);
    }

    public function renew(Request $request, ManagedDomain $domain, DomainOrderService $orders): RedirectResponse
    {
        abort_unless($domain->customer_id === $request->user('customer')->id, 404);
        $data = $request->validate(['period' => ['required', 'integer', 'between:1,10']]);
        abort_unless($domain->status === 'active' && $domain->expires_at?->isFuture(), 422, 'برای تمدید دامنه منقضی‌شده با پشتیبانی تماس بگیرید.');
        $order = $orders->create($request->user('customer'), $domain->name, (int) $data['period'], $domain);

        return $this->redirectTo('customer.domains.orders.show', ['order' => $order, 'step' => 'review']);
    }

    public function invoice(Request $request, DomainOrder $order): View
    {
        $this->own($request, $order);
        abort_unless($order->invoice_id, 404);

        return view('customer.domains.invoice', ['customer' => $request->user('customer'), 'order' => $order, 'invoice' => $order->invoice->load('items'), 'wallets' => app(WalletService::class)]);
    }

    private function redirectTo(string $name, mixed $parameters = []): RedirectResponse
    {
        return redirect()->to(route($name, $parameters, false));
    }

    private function own(Request $request, DomainOrder $order): void
    {
        abort_unless($order->customer_id === $request->user('customer')->id, 404);
    }
}
