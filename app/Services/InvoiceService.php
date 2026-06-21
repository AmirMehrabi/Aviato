<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\UsageAccrual;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Collection as SupportCollection;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function previousMonthPeriod(?CarbonInterface $now = null): array
    {
        $now = CarbonImmutable::instance(($now ?? now())->toImmutable());
        $start = $now->startOfMonth()->subMonth();
        $end = $start->endOfMonth();

        return [$start, $end];
    }

    public function generateMonthlyInvoices(?CarbonInterface $forMonth = null): Collection
    {
        [$periodStart, $periodEnd] = $this->previousMonthPeriod($forMonth);
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
            ->whereBetween('service_date', [$periodStart->toDateString(), $periodEnd->toDateString()])
            ->get();
        $legacyTransactions = WalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransaction::TYPE_CHARGE)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get()
            ->filter(fn (WalletTransaction $transaction): bool => $transaction->isUsageCharge())
            ->values();

        if ($accruals->isEmpty() && $legacyTransactions->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($customer, $periodStart, $periodEnd, $accruals, $legacyTransactions): Invoice {
            $lines = $this->invoiceLines($accruals, $legacyTransactions);
            $subtotal = (int) $lines->sum('subtotal');
            $invoice = Invoice::create([
                'customer_id' => $customer->id,
                'number' => $this->numberFor($customer, $periodStart),
                'status' => Invoice::STATUS_ISSUED,
                'period_start' => $periodStart,
                'period_end' => $periodEnd,
                'issued_at' => now(),
                'currency' => AppSetting::currency(),
                'subtotal_amount' => $subtotal,
                'wallet_charged_amount' => $subtotal,
                'adjustment_amount' => 0,
                'total_amount' => $subtotal,
                'meta' => [
                    'usage_accrual_ids' => $accruals->pluck('id')->all(),
                    'legacy_transaction_ids' => $legacyTransactions->pluck('id')->all(),
                    'generated_via' => 'billing:generate-monthly-invoices',
                ],
            ]);

            $lines->each(fn (array $line) => $this->createInvoiceItem($invoice, $line));

            if ($accruals->isNotEmpty()) {
                UsageAccrual::query()->whereKey($accruals->modelKeys())->delete();
            }

            return $invoice->load('items');
        });
    }

    private function invoiceLines(Collection $accruals, SupportCollection $legacyTransactions): SupportCollection
    {
        $entries = collect();

        foreach ($accruals as $accrual) {
            $entries->push([
                'key' => implode('|', [$accrual->category, $accrual->resource_type, $accrual->resource_id]),
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

            return [
                'category' => $first['category'],
                'virtual_machine_id' => $first['virtual_machine_id'],
                'resource_name' => $first['resource_name'],
                'hours' => $seconds / 3600,
                'unit_price' => $seconds > 0 ? $subtotal / ($seconds / 3600) : 0,
                'subtotal' => $subtotal,
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
            default => '',
        };
        $start = $line['period_start'] ? CarbonImmutable::parse($line['period_start'])->format('Y/m/d H:i') : '—';
        $end = $line['period_end'] ? CarbonImmutable::parse($line['period_end'])->format('Y/m/d H:i') : '—';
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
            'quantity' => round($line['hours'], 4),
            'unit' => 'hour',
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
            ],
        ]);
    }

    private function numberFor(Customer $customer, CarbonInterface $periodStart): string
    {
        return sprintf('INV-%s-%04d', $periodStart->format('Ym'), $customer->id);
    }
}
