<?php

namespace App\Services\Domains;

use App\Jobs\ProcessDomainOrder;
use App\Models\DomainOperation;
use App\Models\DomainOrder;
use App\Models\ManagedDomain;
use App\Services\Notifications\NotificationEventSubscriber;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Throwable;

class DomainLifecycleService
{
    public function __construct(private readonly DomainOrderService $orders, private readonly DomainManagementService $management) {}

    public function reconcile(): void
    {
        DomainOrder::whereIn('status', ['funded', 'processing', 'confirming'])->orderBy('id')->chunkById(100, function ($orders): void {
            foreach ($orders as $order) {
                ProcessDomainOrder::dispatch($order->id);
            }
        });
        // A killed worker may leave a persisted mutation with no result. Do not resubmit it.
        DomainOperation::where('status', 'submitting')->where('created_at', '<', now()->subMinutes(5))->get()->each(function ($operation): void {
            $operation->update(['status' => 'uncertain']);
            $operation->order?->update(['status' => 'needs_review']);
        });
        DomainOperation::where('kind', 'nameservers')->whereIn('status', ['pending', 'uncertain'])->get()->each(fn ($operation) => $this->management->reconcile($operation));
    }

    public function daily(): void
    {
        ManagedDomain::orderBy('id')->chunkById(100, function ($domains): void {
            foreach ($domains as $domain) {
                try {
                    $this->management->sync($domain);
                } catch (Throwable) { /* Keep known expiry and retry next run. */
                }
                if (! $domain->expires_at) {
                    continue;
                }
                $days = (int) now()->startOfDay()->diffInDays($domain->expires_at->copy()->startOfDay(), false);
                $cycle = $domain->expires_at->format('Y-m-d');
                foreach ([1, 7, 14, 30] as $threshold) {
                    if ($days <= $threshold && $days > 0) {
                        $this->notice($domain, $cycle.':expiry:'.$threshold, 'دامنه '.$domain->name.' در '.$days.' روز آینده منقضی می‌شود. برای تمدید، موجودی و تنظیمات را بررسی کنید.');
                        break;
                    }
                }
                if ($days <= 0) {
                    $this->notice($domain, $cycle.':expired', 'دامنه '.$domain->name.' منقضی شده است. برای بررسی امکان تمدید یا بازیابی با پشتیبانی تماس بگیرید.');
                }
                if ($domain->auto_renew && $domain->auto_renew_consented_at && $days > 0 && $days <= 7 && ! $domain->customer->isSuspended()) {
                    $this->autoRenew($domain, $cycle);
                }
            }
        });
    }

    private function autoRenew(ManagedDomain $domain, string $cycle): void
    {
        Cache::lock('domain-auto-renew-'.$domain->id, 120)->get(function () use ($domain, $cycle): void {
            if (DomainOrder::where('managed_domain_id', $domain->id)->where('operation', 'renew')->whereIn('status', ['funded', 'processing', 'confirming', 'needs_review', 'awaiting_payment'])->exists()) {
                return;
            }
            // Limit definitive failures to one attempt per day. Unknown results never generate retries.
            if (DomainOrder::where('managed_domain_id', $domain->id)->where('operation', 'renew')->where('status', 'failed')->whereDate('updated_at', today())->exists()) {
                return;
            }
            try {
                $order = $this->orders->create($domain->customer, $domain->name, 1, $domain);
                if ($order->quoted_until->isPast()) {
                    $this->orders->refreshQuote($order);
                    $order->refresh();
                }
                $totalRial = $order->currency === 'IRT' ? $order->total * 10 : $order->total;
                if (! $domain->auto_renew_limit || $totalRial > $domain->auto_renew_limit) {
                    $this->notice($domain, $cycle.':limit:'.today()->toDateString(), 'قیمت تمدید '.$domain->name.' از سقف مجاز شما بیشتر است. برای بررسی قیمت و تمدید دستی وارد پنل شوید.');

                    return;
                }
                if ($this->orders->available($domain->customer) < $order->total) {
                    $this->notice($domain, $cycle.':funds:'.today()->toDateString(), 'موجودی کیف پول برای تمدید '.$domain->name.' کافی نیست. برای جلوگیری از انقضا کیف پول را شارژ کنید.');

                    return;
                }
                $order->update(['consented_at' => now()]);
                $this->orders->pay($order->fresh(), null, walletOnly: true);
            } catch (Throwable) {
                $this->notice($domain, $cycle.':error:'.today()->toDateString(), 'تمدید خودکار '.$domain->name.' هنوز انجام نشده است. وضعیت و قیمت را در پنل بررسی کنید.');
            }
        });
    }

    private function notice(ManagedDomain $domain, string $key, string $body): void
    {
        $inserted = DB::table('domain_notices')->insertOrIgnore(['managed_domain_id' => $domain->id, 'key' => $key, 'created_at' => now(), 'updated_at' => now()]);
        if ($inserted) {
            app(NotificationEventSubscriber::class)->notify($domain->customer, 'domain_expiry', ['body' => $body], route('customer.domains.show', $domain));
        }
    }
}
