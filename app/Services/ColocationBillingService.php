<?php

namespace App\Services;

use App\Models\ColocationPlan;
use App\Models\ColocationService;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Project;
use App\Notifications\ColocationNotification;
use App\Support\Jalali;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ColocationBillingService
{
    public function order(Project $project, ColocationPlan $plan, string $interval, string $name, string $checkoutKey): ColocationService
    {
        return DB::transaction(function () use ($project, $plan, $interval, $name, $checkoutKey): ColocationService {
            $owner = $project->owner()->lockForUpdate()->firstOrFail();
            $plan = ColocationPlan::lockForUpdate()->findOrFail($plan->id);
            abort_unless($plan->customer_id === $owner->id && $owner->colocation_enabled && ! $owner->isSuspended() && $plan->available, 404);
            if ($existing = ColocationService::where('checkout_key', $checkoutKey)->first()) {
                abort_unless($existing->project_id === $project->id && $existing->colocation_plan_id === $plan->id && $existing->billing_interval === $interval, 409);

                return $existing;
            }
            if (! array_key_exists($interval, $plan->intervals())) {
                throw ValidationException::withMessages(['billing_interval' => 'این دوره پرداخت برای پلن شما فعال نیست.']);
            }
            $agreement = $plan->only(['name', 'location', 'rack_units', 'power', 'connectivity', 'traffic_policy', 'included_traffic_gb', 'traffic_price_per_gb', 'currency', 'setup_fee', 'tax_rate', 'terms', 'delivery_instructions']);
            $agreement['recurring_price'] = $plan->{$interval.'_price'};
            $agreement['calendar'] = 'gregorian_anniversary';
            $agreement['timezone'] = 'Asia/Tehran';
            $service = ColocationService::create([
                'checkout_key' => $checkoutKey, 'customer_id' => $owner->id, 'project_id' => $project->id,
                'colocation_plan_id' => $plan->id, 'name' => $name, 'billing_interval' => $interval,
                'agreement' => $agreement, 'accepted_at' => now(),
            ]);
            $this->invoice($service, 'initial', [
                ['label' => 'پیش‌پرداخت اولین دوره — '.$plan->name, 'quantity' => 1, 'unit_price' => $agreement['recurring_price'], 'unit' => $interval],
                ...($plan->setup_fee > 0 ? [['label' => 'نصب و راه‌اندازی (یک‌بار)', 'quantity' => 1, 'unit_price' => $plan->setup_fee, 'unit' => 'setup']] : []),
            ], null, null, now()->addDays(7)->toImmutable(), ['initial' => true, 'coverage_pending' => true]);
            $this->event($service, 'ordered', 'سفارش ثبت شد. پس از پرداخت، مراحل نصب آغاز می‌شود.');

            return $service;
        });
    }

    public function pay(Invoice $invoice, string $provider): Payment
    {
        return DB::transaction(function () use ($invoice, $provider): Payment {
            // Same lock order as payment completion: service, invoice, payment.
            $service = ColocationService::lockForUpdate()->findOrFail($invoice->colocation_service_id);
            $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
            abort_unless($invoice->kind === 'colocation' && $invoice->status === 'issued', 409);
            abort_if($service->status === 'cancelled' && ($invoice->meta['initial'] ?? false), 409);
            if ($pending = Payment::where('invoice_id', $invoice->id)->where('status', Payment::STATUS_PENDING)->first()) {
                return $pending;
            }

            return app(PaymentService::class)->createInvoicePayment($invoice, $provider);
        });
    }

    public function paymentCompleted(Payment $payment): void
    {
        abort_unless($payment->isSuccessful() && $payment->type === Payment::TYPE_INVOICE, 409);
        $invoice = Invoice::lockForUpdate()->findOrFail($payment->invoice_id);
        if ($invoice->status === 'paid') {
            return;
        }
        if ($invoice->status !== 'issued') {
            throw ValidationException::withMessages(['payment' => 'این صورتحساب دیگر قابل پرداخت نیست.']);
        }
        $expected = $invoice->currency === 'IRT' ? $invoice->total_amount * 10 : $invoice->total_amount;
        if ($payment->amount !== $expected || $payment->currency !== 'IRR' || $invoice->customer_id !== $payment->customer_id) {
            throw ValidationException::withMessages(['payment' => 'مبلغ یا مالک پرداخت با صورتحساب مطابقت ندارد.']);
        }
        $invoice->update(['status' => 'paid', 'paid_at' => $payment->paid_at]);
        $service = ColocationService::findOrFail($invoice->colocation_service_id);
        if ($invoice->meta['initial'] ?? false) {
            $service->update(['status' => 'awaiting_installation', 'paid_at' => $payment->paid_at]);
        }
        $this->refreshPaidThrough($service);
        $this->event($service, 'paid', 'پرداخت صورتحساب '.$invoice->number.' تأیید شد.');
    }

    public function progress(ColocationService $service, string $status, string $message, ?string $equipment, int $adminId): void
    {
        DB::transaction(function () use ($service, $status, $message, $equipment, $adminId): void {
            $service = ColocationService::lockForUpdate()->findOrFail($service->id);
            $steps = ['awaiting_installation', 'received', 'installing', 'testing'];
            abort_unless(in_array($service->status, $steps, true) && in_array($status, $steps, true), 409);
            if (array_search($status, $steps, true) < array_search($service->status, $steps, true)) {
                throw ValidationException::withMessages(['status' => 'مراحل نصب را به مرحله قبل برنگردانید.']);
            }
            $service->update(['status' => $status, 'equipment' => $equipment]);
            $this->event($service, 'installation', $message, $adminId);
        });
    }

    public function activate(ColocationService $service, CarbonImmutable $start, string $reason, int $adminId): void
    {
        DB::transaction(function () use ($service, $start, $reason, $adminId): void {
            $service = ColocationService::lockForUpdate()->findOrFail($service->id);
            abort_unless(in_array($service->status, ['awaiting_installation', 'received', 'installing', 'testing'], true) && $service->paid_at && ! $service->billing_starts_at, 409);
            if ($start->lt($service->paid_at->setTimezone('Asia/Tehran')->startOfDay())) {
                throw ValidationException::withMessages(['billing_starts_at' => 'شروع صورتحساب نمی‌تواند پیش از پرداخت اولیه باشد.']);
            }
            if ($start->lt(now('Asia/Tehran')->startOfDay()) && trim($reason) === '') {
                throw ValidationException::withMessages(['reason' => 'برای شروع در گذشته، دلیل را وارد کنید.']);
            }
            $service->update(['status' => 'active', 'installed_at' => now(), 'billing_starts_at' => $start->setTimezone(config('app.timezone'))]);
            $initial = $service->invoices()->where('colocation_key', $service->uuid.':initial')->lockForUpdate()->firstOrFail();
            $meta = $initial->meta;
            $meta['coverage_pending'] = false;
            // The prepaid invoice has no promised dates until installation is completed.
            $initial->update(['period_start' => $service->boundary(0), 'period_end' => $service->boundary(1), 'meta' => $meta]);
            $this->refreshPaidThrough($service);
            $this->event($service, 'activated', 'نصب تکمیل شد. تاریخ شروع صورتحساب: '.Jalali::format($start, 'Y/m/d').($reason !== '' ? ' — '.$reason : ''), $adminId, ['billing_starts_at' => $start->toIso8601String()]);
        });
    }

    public function generateRenewals(): int
    {
        $count = 0;
        ColocationService::where('status', 'active')->whereNotNull('ends_at')->where('ends_at', '<=', now())->update(['status' => 'cancelled']);
        ColocationService::where('status', 'active')->whereNotNull('billing_starts_at')->eachById(function (ColocationService $service) use (&$count): void {
            DB::transaction(function () use ($service, &$count): void {
                $service = ColocationService::lockForUpdate()->findOrFail($service->id);
                $period = $service->next_period;
                // Recover missed scheduler runs without moving the original anniversary.
                for ($batch = 0; $batch < 120; $batch++, $period++) {
                    $start = $service->boundary($period);
                    $end = $service->boundary($period + 1);
                    if ($start->gt(now()->addDays(14)) || ($service->ends_at && $start->gte($service->ends_at))) {
                        break;
                    }
                    if ($service->ends_at && $end->gt($service->ends_at)) {
                        break;
                    }
                    $this->invoice($service, 'renewal:'.$period, [['label' => 'تمدید '.$service->agreement['name'], 'quantity' => 1, 'unit_price' => $service->agreement['recurring_price'], 'unit' => $service->billing_interval]], $start, $end, $start, ['period' => $period]);
                    $count++;
                }
                $service->update(['next_period' => $period]);
            });
        });

        return $count;
    }

    public function traffic(ColocationService $service, int $month, int $usageMb, string $source, int $adminId): Invoice
    {
        return DB::transaction(function () use ($service, $month, $usageMb, $source, $adminId): Invoice {
            $service = ColocationService::lockForUpdate()->findOrFail($service->id);
            abort_unless($service->billing_starts_at && in_array($service->agreement['traffic_policy'], ['included', 'metered'], true), 409);
            $start = $service->billing_starts_at->setTimezone('Asia/Tehran')->addMonthsNoOverflow($month)->setTimezone(config('app.timezone'));
            $end = $service->billing_starts_at->setTimezone('Asia/Tehran')->addMonthsNoOverflow($month + 1)->setTimezone(config('app.timezone'));
            abort_if($end->isFuture() || ($service->ends_at && $start->gte($service->ends_at)), 422);
            if ($service->ends_at && $end->gt($service->ends_at)) {
                $end = $service->ends_at;
            }
            if ($existing = $service->invoices()->where('colocation_key', $service->uuid.':traffic:'.$month)->first()) {
                return $existing;
            }
            $includedMb = $service->agreement['traffic_policy'] === 'included' ? $service->agreement['included_traffic_gb'] * 1000 : 0;
            $billableMb = max(0, $usageMb - $includedMb);
            $rate = $service->agreement['traffic_price_per_gb'];
            $amount = intdiv($billableMb, 1000) * $rate + intdiv(($billableMb % 1000) * $rate + 500, 1000);
            $invoice = $this->invoice($service, 'traffic:'.$month, [['label' => 'ترافیک خروجی مازاد (GB)', 'quantity' => $billableMb / 1000, 'unit_price' => $service->agreement['traffic_price_per_gb'], 'amount' => $amount, 'unit' => 'GB']], $start, $end, now()->addDays(7)->toImmutable(), ['traffic' => true, 'usage_mb' => $usageMb, 'included_mb' => $includedMb, 'source' => $source, 'reviewed_by' => $adminId]);
            $this->event($service, 'traffic', 'مصرف نهایی ترافیک این دوره ثبت شد؛ جزئیات در صورتحساب موجود است.', $adminId);

            return $invoice;
        });
    }

    public function end(ColocationService $service, CarbonImmutable $end, string $message, int $adminId): void
    {
        DB::transaction(function () use ($service, $end, $message, $adminId): void {
            $end = $end->setTimezone(config('app.timezone'));
            $service = ColocationService::lockForUpdate()->findOrFail($service->id);
            abort_unless($service->status === 'active' && $service->billing_starts_at, 409);
            // End at a paid or invoiced boundary; early termination needs a separate credit review.
            $valid = $service->invoices()->where('status', 'paid')->where('colocation_key', 'not like', '%:traffic:%')->where('period_end', $end->setTimezone(config('app.timezone')))->exists();
            if (! $valid || $end->lte($service->billing_starts_at) || $end->lt(now()->startOfDay())) {
                throw ValidationException::withMessages(['ends_at' => 'پایان سرویس را در انتهای یک دوره پرداخت‌شده، امروز یا در آینده تعیین کنید.']);
            }
            if ($service->invoices()->where('period_end', '>', $end)->where('status', 'paid')->where('colocation_key', 'not like', '%:traffic:%')->exists()) {
                throw ValidationException::withMessages(['ends_at' => 'ابتدا وضعیت دوره‌های پرداخت‌شده بعدی را بررسی کنید.']);
            }
            if (Payment::whereHas('invoice', fn ($q) => $q->where('colocation_service_id', $service->id)->where('period_start', '>=', $end)->where('colocation_key', 'like', $service->uuid.':renewal:%'))->where('status', 'pending')->exists()) {
                throw ValidationException::withMessages(['ends_at' => 'یک پرداخت تمدید در جریان است؛ پیش از تأیید پایان، نتیجه آن را تعیین کنید.']);
            }
            $service->update(['ends_at' => $end->setTimezone(config('app.timezone'))]);
            $service->invoices()->where('period_start', '>=', $end)->where('status', 'issued')->where('colocation_key', 'like', $service->uuid.':renewal:%')->update(['status' => 'void']);
            $this->event($service, 'ending', $message, $adminId, ['ends_at' => $end->toIso8601String()]);
        });
    }

    public function sendReminders(): int
    {
        $count = 0;
        Invoice::where('kind', 'colocation')->where('status', 'issued')->where('due_at', '<=', now()->addDays(3))->eachById(function (Invoice $invoice) use (&$count): void {
            DB::transaction(function () use ($invoice, &$count): void {
                $service = ColocationService::lockForUpdate()->findOrFail($invoice->colocation_service_id);
                $invoice = Invoice::lockForUpdate()->findOrFail($invoice->id);
                if ($invoice->status !== 'issued') {
                    return;
                }
                $stage = $invoice->due_at->isPast() ? 'overdue' : 'due';
                $kind = 'reminder:'.$invoice->id.':'.$stage;
                if ($service->events()->where('kind', $kind)->exists()) {
                    return;
                }
                $message = ($stage === 'overdue' ? 'مهلت پرداخت صورتحساب گذشته است: ' : 'یادآوری سررسید صورتحساب: ').$invoice->number.' — '.app(WalletService::class)->format($invoice->total_amount, $invoice->currency).'.';
                $this->event($service, $kind, $message);
                DB::afterCommit(fn () => $service->customer->notify(new ColocationNotification($service, $message, $invoice->id)));
                $count++;
            });
        });

        return $count;
    }

    public function event(ColocationService $service, string $kind, string $message, ?int $adminId = null, array $meta = []): void
    {
        $service->events()->create(['kind' => $kind, 'message' => $message, 'admin_id' => $adminId, 'meta' => $meta]);
        if (in_array($kind, ['paid', 'installation', 'activated', 'ending'], true)) {
            DB::afterCommit(fn () => $service->customer->notify(new ColocationNotification($service, $message)));
        }
    }

    private function refreshPaidThrough(ColocationService $service): void
    {
        if (! $service->billing_starts_at) {
            return;
        }
        $through = $service->boundary(0);
        foreach ($service->invoices()->where('status', 'paid')->where('colocation_key', 'not like', '%:traffic:%')->orderBy('period_start')->get() as $invoice) {
            if ($invoice->period_start->gt($through)) {
                break;
            }
            if ($invoice->period_end->gt($through)) {
                $through = $invoice->period_end->toImmutable();
            }
        }
        $service->update(['paid_through' => $through]);
    }

    private function invoice(ColocationService $service, string $key, array $lines, ?CarbonImmutable $start, ?CarbonImmutable $end, CarbonImmutable $due, array $meta = []): Invoice
    {
        $key = $service->uuid.':'.$key;
        if ($existing = Invoice::where('colocation_key', $key)->first()) {
            return $existing;
        }
        $subtotal = collect($lines)->sum(fn ($line) => $line['amount'] ?? $line['quantity'] * $line['unit_price']);
        $tax = (int) round($subtotal * $service->agreement['tax_rate'] / 100);
        $total = (int) $subtotal + $tax;
        $invoice = Invoice::create([
            'customer_id' => $service->customer_id, 'kind' => 'colocation', 'billing_key' => $key,
            'colocation_service_id' => $service->id, 'colocation_key' => $key,
            'number' => 'CO-'.strtoupper(Str::random(12)), 'status' => $total === 0 ? 'paid' : 'issued',
            'currency' => $service->agreement['currency'], 'period_start' => $start, 'period_end' => $end,
            'issued_at' => now(), 'due_at' => $due, 'paid_at' => $total === 0 ? now() : null,
            'subtotal_amount' => $subtotal, 'tax_amount' => $tax, 'tax_rate_percentage' => $service->agreement['tax_rate'],
            'total_amount' => $total, 'meta' => array_merge($meta, ['project_id' => $service->project_id]),
        ]);
        foreach ($lines as $line) {
            $invoice->items()->create(['type' => 'colocation', 'label' => $line['label'], 'quantity' => $line['quantity'], 'unit' => $line['unit'], 'unit_price' => $line['unit_price'], 'subtotal' => $line['amount'] ?? $line['quantity'] * $line['unit_price'], 'meta' => ['project_id' => $service->project_id]]);
        }
        DB::afterCommit(fn () => $service->customer->notify(new ColocationNotification($service, 'صورتحساب '.$invoice->number.' صادر شد.', $invoice->id)));

        return $invoice;
    }
}
