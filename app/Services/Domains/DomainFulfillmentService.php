<?php

namespace App\Services\Domains;

use App\Models\DomainContact;
use App\Models\DomainOperation;
use App\Models\DomainOrder;
use App\Models\Invoice;
use App\Models\ManagedDomain;
use App\Models\WalletTransaction;
use App\Services\Notifications\NotificationEventSubscriber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Throwable;

class DomainFulfillmentService
{
    public function __construct(private readonly DomainProvider $provider, private readonly DomainOrderService $orders) {}

    public function process(DomainOrder $order): void
    {
        if (! $order->isProcessing() || $order->status === 'needs_review') {
            return;
        }
        if ($order->status === 'funded') {
            try {
                $this->orders->assertPurchasableForFulfillment($order);
                $this->orders->validateSupplier($order);
            } catch (Throwable $exception) {
                $message = $exception instanceof ValidationException ? $exception->validator->errors()->first() : 'امکان تأیید قیمت یا موجودی دامنه نبود. مبلغ رزرو به کیف پول شما بازگشت.';
                $this->orders->fail($order, $message);

                return;
            }
            $order->update(['status' => 'processing']);
        }
        if ($order->operation === 'register' && $order->contact_mode === 'contact' && empty($order->registrant['provider_handle'])) {
            $contact = $this->operation($order, 'contact', 'POST', '/contact/create', ['tld' => $order->product->extension] + $order->registrant);
            if (! $contact || $contact->status !== 'done') {
                return;
            }
            try {
                $details = $this->provider->request('POST', '/contact/info', ['tld' => $order->product->extension, 'object' => $order->registrant['email']]);
                $handle = $details['contact'] ?? null;
                if (isset($details['email']) && strcasecmp((string) $details['email'], $order->registrant['email']) !== 0) {
                    throw new \RuntimeException('Contact identity mismatch.');
                }
                if (! is_string($handle) || ! preg_match('/^[a-zA-Z0-9@._-]{1,150}$/', $handle)) {
                    $order->update(['status' => 'needs_review', 'customer_message' => 'اطلاعات مالک در حال بررسی است. نیازی به پرداخت دوباره نیست.']);

                    return;
                }
            } catch (Throwable) {
                $order->update(['status' => 'confirming']);

                return;
            }
            $registrant = $order->registrant;
            $registrant['provider_handle'] = $handle;
            $order->update(['registrant' => $registrant]);
            foreach (DomainContact::where('customer_id', $order->customer_id)->get() as $profile) {
                $profileDetails = $profile->details;
                $baseDetails = $profileDetails;
                unset($baseDetails['provider_handles']);
                $comparison = $registrant;
                unset($comparison['provider_handle']);
                if ($baseDetails == $comparison) {
                    $profileDetails['provider_handles'][$order->product->extension] = $handle;
                    $profile->update(['details' => $profileDetails]);
                }
            }
            $handles = array_fill_keys(['holder', 'billing', 'admin', 'tech'], $handle);
        } elseif ($order->contact_mode === 'contact' && ! empty($order->registrant['provider_handle'])) {
            $handles = array_fill_keys(['holder', 'billing', 'admin', 'tech'], $order->registrant['provider_handle']);
        } else {
            $handles = array_intersect_key($order->registrant ?? [], array_flip(['holder', 'billing', 'admin', 'tech']));
        }
        // confirmPrice=false never grants permission for an unquoted extra supplier charge.
        $payload = ['period' => $order->period, 'confirmPrice' => false];
        if ($order->operation === 'register') {
            $payload += ['domain' => $order->domain, 'dnsList' => $order->nameservers] + $handles;
        }
        if (! $order->operations()->where('kind', $order->operation)->exists()) {
            try {
                $this->orders->assertPurchasableForFulfillment($order);
                $this->orders->validateSupplier($order);
            } catch (Throwable) {
                $this->orders->fail($order, 'قیمت یا وضعیت دامنه پیش از ارسال قابل تأیید نبود. مبلغ رزرو به کیف پول بازگشت.');

                return;
            }
        }
        $operation = $this->operation($order, $order->operation,
            $order->operation === 'register' ? 'POST' : 'PUT',
            $order->operation === 'register' ? '/domain/register' : '/domain/'.$order->domain.'/renew', $payload);
        if ($operation?->status === 'done') {
            $this->complete($order);
        }
    }

    private function operation(DomainOrder $order, string $kind, string $method, string $path, array $payload): ?DomainOperation
    {
        $operation = $order->operations()->where('kind', $kind)->first();
        if (! $operation) {
            // Persist the attempt BEFORE sending. A crash after this point requires reconciliation.
            $operation = $order->operations()->create(['kind' => $kind, 'status' => 'submitting']);
            try {
                $result = $this->provider->request($method, $path, $payload);
                if (! isset($result['trackId']) || ! preg_match('/^[0-9]+$/', (string) $result['trackId'])) {
                    throw new \RuntimeException('No tracking id.');
                }
                $operation->update(['track_id' => (string) $result['trackId'], 'status' => 'pending']);
            } catch (Throwable) {
                $operation->update(['status' => 'uncertain']);
                $order->update(['status' => 'needs_review', 'customer_message' => 'نتیجه درخواست در حال پیگیری با سرویس‌دهنده است. مبلغ محفوظ است؛ دوباره پرداخت نکنید.']);

                return null;
            }
        }
        if (in_array($operation->status, ['submitting', 'uncertain'], true)) {
            $order->update(['status' => 'needs_review']);

            return null;
        }
        if ($operation->status === 'pending') {
            try {
                $result = $this->provider->inquiry($operation->track_id);
                $expectedJob = $kind === 'contact' ? 'CreateContactJob' : ($kind === 'renew' ? 'RenewJob' : 'RegisterJob');
                if (($result['job'] ?? null) !== $expectedJob || ($result['item'] ?? null) !== ($kind === 'contact' ? $order->registrant['email'] : $order->domain)) {
                    $operation->update(['status' => 'uncertain']);
                    $order->update(['status' => 'needs_review']);

                    return null;
                }
                $state = $result['status'] ?? 'pending';
                $operation->update(['checked_at' => now(), 'result_code' => substr((string) $state, 0, 100)]);
                if ($state === 'failed') {
                    $operation->update(['status' => 'failed']);
                    $this->orders->fail($order, 'سرویس‌دهنده انجام سفارش را تأیید نکرد. مبلغ رزرو به کیف پول شما بازگشت.');

                    return null;
                }
                if (in_array($state, ['done', 'completed', 'successful', 'success'], true)) {
                    $operation->update(['status' => 'done']);
                } else {
                    $order->update(['status' => 'confirming']);
                }
            } catch (Throwable) {
                $order->update(['status' => 'confirming']);

                return null;
            }
        }

        return $operation->fresh();
    }

    private function complete(DomainOrder $order): void
    {
        try {
            $info = $this->provider->info($order->domain);
            $expiry = Carbon::parse($info['expire_at']);
            $start = $order->operation === 'renew' ? $order->expiry_before : $order->funded_at;
            if (! $start || $expiry->isPast() || $expiry->lt($start->copy()->addYearsNoOverflow($order->period)->subDays(2))
                || ($order->operation === 'renew' && ($expiry->lte($order->expiry_before) || (string) $info['id'] !== (string) $order->managedDomain->provider_id))) {
                throw new \RuntimeException('The full purchased period is not yet confirmed.');
            }
        } catch (Throwable) {
            $order->update(['status' => 'confirming']);

            return;
        }
        DB::transaction(function () use ($order, $info, $expiry): void {
            $order = DomainOrder::lockForUpdate()->findOrFail($order->id);
            if ($order->status === 'completed') {
                return;
            }
            $domain = $order->managedDomain;
            if (! $domain) {
                $domain = ManagedDomain::create(['uuid' => (string) Str::uuid(), 'customer_id' => $order->customer_id, 'name' => $order->domain, 'extension' => $order->product->extension, 'registered_at' => now(), 'registrant' => $order->registrant, 'nameservers' => $order->nameservers]);
            }
            $domain->update(['provider_id' => (string) $info['id'], 'expires_at' => $expiry, 'synced_at' => now(), 'status' => 'active']);
            $transaction = WalletTransaction::lockForUpdate()->findOrFail($order->wallet_transaction_id);
            $transaction->update(['type' => WalletTransaction::TYPE_CHARGE, 'description' => ($order->operation === 'renew' ? 'تمدید دامنه ' : 'ثبت دامنه ').$order->domain, 'metadata' => ['category' => 'domain_'.$order->operation, 'domain_order_id' => $order->id, 'tax_amount' => $order->tax_amount]]);
            $invoice = Invoice::create(['customer_id' => $order->customer_id, 'kind' => 'domain', 'billing_key' => null, 'domain_order_id' => $order->id, 'number' => 'DOM-'.str_pad((string) $order->id, 8, '0', STR_PAD_LEFT), 'status' => Invoice::STATUS_ISSUED, 'period_start' => $order->created_at, 'period_end' => $expiry, 'issued_at' => now(), 'currency' => $order->currency, 'subtotal_amount' => $order->subtotal, 'tax_amount' => $order->tax_amount, 'tax_rate_percentage' => $order->tax_rate, 'wallet_charged_amount' => $order->total, 'adjustment_amount' => 0, 'total_amount' => $order->total, 'meta' => ['domain_order_id' => $order->id, 'operation' => $order->operation]]);
            $invoice->items()->create(['type' => 'domain_'.$order->operation, 'label' => $transaction->description, 'description' => $order->period.' سال', 'quantity' => 1, 'unit' => 'سفارش', 'unit_price' => $order->subtotal, 'subtotal' => $order->subtotal, 'meta' => ['domain' => $order->domain, 'period' => $order->period]]);
            if ($order->tax_amount) {
                $invoice->items()->create(['type' => 'tax', 'label' => 'مالیات و عوارض', 'quantity' => 1, 'unit' => 'سفارش', 'unit_price' => $order->tax_amount, 'subtotal' => $order->tax_amount]);
            }
            $order->update(['managed_domain_id' => $domain->id, 'invoice_id' => $invoice->id, 'status' => 'completed', 'completed_at' => now(), 'customer_message' => 'سفارش با موفقیت انجام شد.']);
            DB::table('domain_claims')->where('domain_order_id', $order->id)->delete();
            DB::afterCommit(fn () => app(NotificationEventSubscriber::class)->notify($order->customer, 'domain_order', ['body' => 'سفارش دامنه '.$order->domain.' انجام شد. فاکتور در پنل در دسترس است.'], route('customer.domains.orders.show', $order)));
        });
    }
}
