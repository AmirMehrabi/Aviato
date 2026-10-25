<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\NetworkUsageBucket;
use App\Models\UsageAccrual;
use App\Models\WalletTransaction;
use App\Support\Jalali;
use Carbon\Carbon;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;
use Morilog\Jalali\Jalalian;

class InvoiceService
{
    public function previousMonthPeriod(?CarbonInterface $now = null): array
    {
        $local = Carbon::instance($now ?? now())->setTimezone('Asia/Tehran');
        $month = Jalalian::fromCarbon($local)->getFirstDayOfMonth();
        $start = $month->subMonths()->toCarbon()->startOfDay()->toImmutable();
        $end = $month->toCarbon()->startOfDay()->toImmutable()->subMicrosecond();

        return [$start, $end];
    }

    public function isFirstDayOfMonth(?CarbonInterface $now = null): bool
    {
        $local = Carbon::instance($now ?? now())->setTimezone('Asia/Tehran');

        return Jalalian::fromCarbon($local)->getDay() === 1;
    }

    public function generateMonthlyInvoices(?CarbonInterface $forMonth = null): Collection
    {
        [$periodStart, $periodEnd] = $this->previousMonthPeriod($forMonth);
        // Network buckets can finalize after the regular 00:05 daily settlement.
        $billing = app(UsageBillingService::class);
        UsageAccrual::query()
            ->whereNull('settled_at')
            ->whereDate('service_date', '>=', $periodStart->toDateString())
            ->whereDate('service_date', '<=', $periodEnd->toDateString())
            ->select('service_date')->distinct()->orderBy('service_date')->get()
            ->each(fn (UsageAccrual $accrual) => $billing->settleDate($accrual->service_date));

        $invoices = new Collection;

        Customer::query()->orderBy('id')->chunk(100, function ($customers) use ($invoices, $periodStart, $periodEnd): void {
            foreach ($customers as $customer) {
                if ($invoice = $this->generateForCustomer($customer, $periodStart, $periodEnd)) {
                    $invoices->push($invoice);
                }
            }
        });

        return $invoices;
    }

    public function generateForCustomer(Customer $customer, CarbonInterface $periodStart, CarbonInterface $periodEnd): ?Invoice
    {
        $existing = Invoice::query()
            ->where('customer_id', $customer->id)
            ->where('period_start', $periodStart)
            ->where('period_end', $periodEnd)
            ->first();

        if ($existing) {
            return $existing->load('items');
        }

        $accruals = UsageAccrual::query()
            ->where('customer_id', $customer->id)
            ->whereNotNull('settled_at')
            ->whereDate('service_date', '>=', $periodStart->toDateString())
            ->whereDate('service_date', '<=', $periodEnd->toDateString())
            ->get();
        $legacyTransactions = WalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransaction::TYPE_CHARGE)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get()
            ->filter(fn (WalletTransaction $transaction): bool => $transaction->isUsageCharge())
            ->values();

        $networkUsage = NetworkUsageBucket::query()
            ->with('virtualMachine')
            ->where('status', 'final')
            ->where('completeness', 'complete')
            ->where('processing_status', 'rated')
            ->where('interval_start', '>=', $periodStart->toImmutable()->utc())
            ->where('interval_end', '<=', $periodEnd->toImmutable()->addMicrosecond()->utc())
            ->whereHas('ratings.period', fn ($query) => $query->where('customer_id', $customer->id))
            ->get()
            ->groupBy('virtual_machine_id');

        if ($accruals->isEmpty() && $legacyTransactions->isEmpty() && $networkUsage->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($customer, $periodStart, $periodEnd, $accruals, $legacyTransactions, $networkUsage): Invoice {
            $lines = $this->withNetworkUsage($this->invoiceLines($accruals, $legacyTransactions), $networkUsage);
            $subtotal = (int) $lines->sum('subtotal');
            $taxAmount = (int) $lines->sum('tax_amount');
            $taxRate = AppSetting::taxEnabled() ? AppSetting::taxRatePercentage() : 0;
            $total = $subtotal;
            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'number' => $this->numberFor($customer, $periodStart),
                'status' => Invoice::STATUS_ISSUED,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issued_at' => now(),
                'currency' => AppSetting::currency(),
                'subtotal_amount' => $subtotal - $taxAmount,
                'wallet_charged_amount' => $total,
                'adjustment_amount' => 0,
                'total_amount' => $total,
                'tax_amount' => $taxAmount,
                'tax_rate_percentage' => $taxRate,
                'meta' => [
                    'usage_accrual_ids' => $accruals->pluck('id')->all(),
                    'legacy_transaction_ids' => $legacyTransactions->pluck('id')->all(),
                    'generated_via' => 'billing:generate-monthly-invoices',
                    'calendar' => 'jalali',
                    'timezone' => 'Asia/Tehran',
                ],
            ]);

            $lines->each(fn (array $line) => $this->createInvoiceItem($invoice, $line));

            if ($taxAmount > 0) {
                $this->createTaxInvoiceItem($invoice, $taxAmount, $taxRate);
            }

            if ($accruals->isNotEmpty()) {
                UsageAccrual::query()->whereKey($accruals->modelKeys())->delete();
            }

            return $invoice->load('items');
        });
    }

    private function withNetworkUsage(SupportCollection $lines, SupportCollection $networkUsage): SupportCollection
    {
        foreach ($networkUsage as $vmId => $buckets) {
            $index = $lines->search(fn (array $line): bool => $line['category'] === UsageAccrual::CATEGORY_NETWORK
                && (int) $line['virtual_machine_id'] === (int) $vmId);
            $line = $index === false ? [
                'category' => UsageAccrual::CATEGORY_NETWORK,
                'virtual_machine_id' => $vmId,
                'resource_name' => $buckets->first()->virtualMachine?->name ?? 'VM',
                'hours' => 0, 'unit_price' => 0, 'subtotal' => 0,
                'pre_tax_subtotal' => 0, 'tax_amount' => 0, 'tax_rate' => 0, 'tax_exempt' => true,
                'period_start' => $buckets->min('interval_start'),
                'period_end' => $buckets->max('interval_end'),
                'snapshot' => [], 'segments' => [], 'source_ids' => [],
            ] : $lines[$index];
            $line['snapshot']['ingress_bytes'] = (int) $buckets->sum('ingress_bytes');
            $line['snapshot']['egress_bytes'] = (int) $buckets->sum('egress_bytes');
            $line['source_ids']['network_usage_bucket'] = $buckets->modelKeys();

            if ($index === false) {
                $lines->push($line);
            } else {
                $lines[$index] = $line;
            }
        }

        return $lines;
    }

    private function invoiceLines(Collection $accruals, SupportCollection $legacyTransactions): SupportCollection
    {
        $entries = collect();

        foreach ($accruals as $accrual) {
            $entries->push([
                'key' => $accrual->category === UsageAccrual::CATEGORY_NETWORK
                    ? implode('|', [$accrual->category, 'virtual_machine', $accrual->virtual_machine_id])
                    : implode('|', [$accrual->category, $accrual->resource_type, $accrual->resource_id]),
                'category' => $accrual->category,
                'virtual_machine_id' => $accrual->virtual_machine_id,
                'resource_name' => $accrual->resource_name ?: 'VM',
                'seconds' => $accrual->accrued_seconds,
                'amount' => $accrual->amount,
                'period_start' => $accrual->period_start,
                'period_end' => $accrual->period_end,
                'snapshot' => $accrual->snapshot ?? [],
                'segments' => $accrual->segments ?? [],
                'source_id' => $accrual->id,
                'source' => 'usage_accrual',
            ]);
        }

        foreach ($legacyTransactions as $transaction) {
            $meta = $transaction->metadata ?? [];
            $category = $meta['category'] ?? UsageAccrual::CATEGORY_VM;
            $resourceId = $meta['backup_id'] ?? $meta['disk_id'] ?? $meta['vm_id'] ?? 'unknown';
            $resourceType = match ($category) {
                UsageAccrual::CATEGORY_BACKUP => 'vm_backup',
                UsageAccrual::CATEGORY_EXTRA_DISK => 'vm_disk',
                default => 'virtual_machine',
            };
            $snapshot = array_merge(
                $meta['resource_snapshot'] ?? [],
                $meta['backup_snapshot'] ?? [],
                $meta['disk_snapshot'] ?? [],
                [
                    'project_id' => $meta['project_id'] ?? null,
                    'project_name' => $meta['project_name'] ?? null,
                    'project_owner_id' => $meta['project_owner_id'] ?? null,
                    'created_by_customer_id' => $meta['created_by_customer_id'] ?? null,
                ],
            );

            $entries->push([
                'key' => implode('|', [$category, $resourceType, $resourceId]),
                'category' => $category,
                'virtual_machine_id' => $meta['vm_id'] ?? null,
                'resource_name' => $meta['vm_name'] ?? 'VM',
                'seconds' => (int) round(((float) ($meta['hours'] ?? 0)) * 3600),
                'amount' => abs($transaction->amount),
                'period_start' => isset($meta['period_start']) ? CarbonImmutable::parse($meta['period_start']) : $transaction->created_at,
                'period_end' => isset($meta['period_end']) ? CarbonImmutable::parse($meta['period_end']) : $transaction->created_at,
                'snapshot' => $snapshot,
                'segments' => [[
                    'seconds' => (int) round(((float) ($meta['hours'] ?? 0)) * 3600),
                    'hourly_rate' => (float) ($meta['hourly_rate'] ?? 0),
                    'amount' => abs($transaction->amount),
                ]],
                'source_id' => $transaction->id,
                'source' => 'wallet_transaction',
            ]);
        }

        return $entries->groupBy('key')->map(function (SupportCollection $group): array {
            $first = $group->first();
            $seconds = (int) $group->sum('seconds');
            $subtotal = (int) $group->sum('amount');
            $taxExempt = (bool) ($first['snapshot']['tax_exempt'] ?? true);
            $taxRate = $taxExempt ? 0 : AppSetting::taxRatePercentage();
            $taxMultiplier = $taxExempt ? 1 : AppSetting::taxMultiplier();
            $preTaxSubtotal = $taxMultiplier > 1 ? (int) round($subtotal / $taxMultiplier) : $subtotal;
            $taxAmount = $subtotal - $preTaxSubtotal;

            return [
                'category' => $first['category'],
                'virtual_machine_id' => $first['virtual_machine_id'],
                'resource_name' => $first['resource_name'],
                'hours' => $seconds / 3600,
                'unit_price' => $first['category'] === UsageAccrual::CATEGORY_NETWORK ? $preTaxSubtotal : ($seconds > 0 ? $preTaxSubtotal / ($seconds / 3600) : 0),
                'subtotal' => $subtotal,
                'pre_tax_subtotal' => $preTaxSubtotal,
                'tax_amount' => $taxAmount,
                'tax_rate' => $taxRate,
                'tax_exempt' => $taxExempt,
                'period_start' => $group->min('period_start'),
                'period_end' => $group->max('period_end'),
                'snapshot' => $group->last()['snapshot'],
                'segments' => $group->flatMap(fn (array $entry): array => $entry['segments'])->values()->all(),
                'source_ids' => $group->groupBy('source')->map->pluck('source_id')->all(),
            ];
        })->values();
    }

    private function createInvoiceItem(Invoice $invoice, array $line): void
    {
        $snapshot = $line['snapshot'];
        $category = $line['category'];
        $label = $line['resource_name'].match ($category) {
            UsageAccrual::CATEGORY_BACKUP => ' - Backup',
            UsageAccrual::CATEGORY_EXTRA_DISK => ' - Extra Disk',
            UsageAccrual::CATEGORY_NETWORK => ' - Network',
            default => '',
        };
        $start = $line['period_start'] ? Jalali::format(CarbonImmutable::parse($line['period_start'])) : '—';
        $end = $line['period_end'] ? Jalali::format(CarbonImmutable::parse($line['period_end'])) : '—';
        $description = match ($category) {
            UsageAccrual::CATEGORY_BACKUP => sprintf(
                'بازه مصرف بکاپ: %s تا %s | فضای بکاپ: %sGB | Storage: %s',
                $start,
                $end,
                $snapshot['size_gb'] ?? '—',
                $snapshot['storage'] ?? '—',
            ),
            UsageAccrual::CATEGORY_EXTRA_DISK => sprintf(
                'بازه مصرف دیسک اضافه: %s تا %s | دیسک: %s | حجم: %sGB | Storage: %s',
                $start,
                $end,
                $snapshot['disk_device'] ?? '—',
                $snapshot['size_gb'] ?? '—',
                $snapshot['storage'] ?? '—',
            ),
            UsageAccrual::CATEGORY_NETWORK => sprintf(
                'مصرف شبکه از %s تا %s | دریافت: %s GiB | ارسال: %s GiB',
                $start,
                $end,
                isset($snapshot['ingress_bytes']) ? number_format($snapshot['ingress_bytes'] / 1073741824, 4) : '—',
                isset($snapshot['egress_bytes']) ? number_format($snapshot['egress_bytes'] / 1073741824, 4) : '—',
            ),
            default => sprintf(
                'بازه مصرف: %s تا %s | منابع: %s vCPU / %sGB RAM / %sGB Disk / %s IP',
                $start,
                $end,
                $snapshot['cpu_cores'] ?? '—',
                $snapshot['ram_gb'] ?? '—',
                $snapshot['disk_gb'] ?? '—',
                $snapshot['ip_count'] ?? '—',
            ),
        };

        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'virtual_machine_id' => $line['virtual_machine_id'],
            'type' => InvoiceItem::TYPE_VM_USAGE,
            'label' => $label,
            'description' => $description,
            'quantity' => $category === UsageAccrual::CATEGORY_NETWORK ? 1 : round($line['hours'], 4),
            'unit' => $category === UsageAccrual::CATEGORY_NETWORK ? 'period' : 'hour',
            'unit_price' => $line['unit_price'],
            'subtotal' => $line['subtotal'],
            'meta' => [
                'project_id' => $snapshot['project_id'] ?? null,
                'project_name' => $snapshot['project_name'] ?? null,
                'project_owner_id' => $snapshot['project_owner_id'] ?? null,
                'created_by_customer_id' => $snapshot['created_by_customer_id'] ?? null,
                'snapshot' => $snapshot,
                'segments' => $line['segments'],
                'source_ids' => $line['source_ids'],
                'period_start' => $line['period_start'],
                'period_end' => $line['period_end'],
                'category' => $category,
            ],
        ]);
    }

    private function createTaxInvoiceItem(Invoice $invoice, int $taxAmount, float $taxRate): void
    {
        InvoiceItem::create([
            'invoice_id' => $invoice->id,
            'virtual_machine_id' => null,
            'type' => 'tax',
            'label' => sprintf('مالیات (%s٪)', number_format($taxRate, 0)),
            'description' => 'مالیات صورتحساب رسمی',
            'quantity' => 1,
            'unit' => 'fixed',
            'unit_price' => $taxAmount,
            'subtotal' => $taxAmount,
            'meta' => [
                'tax_rate_percentage' => $taxRate,
            ],
        ]);
    }

    private function numberFor(Customer $customer, CarbonInterface $periodStart): string
    {
        return sprintf('INV-%s-%04d', Jalalian::fromCarbon(Carbon::instance($periodStart)->setTimezone('Asia/Tehran'))->format('Ym'), $customer->id);
    }
}
