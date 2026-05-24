<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\WalletTransaction;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
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

        Customer::query()->orderBy('id')->chunk(100, function ($customers) use (&$invoices, $periodStart, $periodEnd): void {
            foreach ($customers as $customer) {
                $invoice = $this->generateForCustomer($customer, $periodStart, $periodEnd);

                if ($invoice) {
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

        $transactions = WalletTransaction::query()
            ->where('customer_id', $customer->id)
            ->where('type', WalletTransaction::TYPE_CHARGE)
            ->whereBetween('created_at', [$periodStart, $periodEnd])
            ->get()
            ->filter(fn (WalletTransaction $transaction): bool => $transaction->isUsageCharge())
            ->values();

        if ($transactions->isEmpty()) {
            return null;
        }

        return DB::transaction(function () use ($customer, $periodStart, $periodEnd, $transactions): Invoice {
            $subtotal = (int) abs($transactions->sum('amount'));

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
                    'transaction_ids' => $transactions->pluck('id')->all(),
                    'generated_via' => 'billing:generate-monthly-invoices',
                ],
            ]);

            $transactions
                ->groupBy(fn (WalletTransaction $transaction): string => (string) ($transaction->metadata['vm_id'] ?? 'unknown'))
                ->each(function ($group) use ($invoice): void {
                    $first = $group->first();
                    $meta = $first->metadata ?? [];
                    $resource = $meta['resource_snapshot'] ?? [];
                    $hours = (float) $group->sum(fn (WalletTransaction $transaction): float => (float) ($transaction->metadata['hours'] ?? 0));
                    $subtotal = (int) abs($group->sum('amount'));
                    $label = $meta['vm_name'] ?? 'VM';
                    $windowStart = collect($group)->map(fn (WalletTransaction $transaction) => $transaction->metadata['period_start'] ?? null)->filter()->sort()->first();
                    $windowEnd = collect($group)->map(fn (WalletTransaction $transaction) => $transaction->metadata['period_end'] ?? null)->filter()->sort()->last();
                    $description = sprintf(
                        'بازه مصرف: %s تا %s | منابع: %s vCPU / %sGB RAM / %sGB Disk / %s IP',
                        $windowStart ? CarbonImmutable::parse($windowStart)->format('Y/m/d H:i') : '—',
                        $windowEnd ? CarbonImmutable::parse($windowEnd)->format('Y/m/d H:i') : '—',
                        $resource['cpu_cores'] ?? '—',
                        $resource['ram_gb'] ?? '—',
                        $resource['disk_gb'] ?? '—',
                        $resource['ip_count'] ?? '—',
                    );

                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'virtual_machine_id' => $meta['vm_id'] ?? null,
                        'type' => InvoiceItem::TYPE_VM_USAGE,
                        'label' => $label,
                        'description' => $description,
                        'quantity' => round($hours, 4),
                        'unit' => 'hour',
                        'unit_price' => (float) ($meta['hourly_rate'] ?? 0),
                        'subtotal' => $subtotal,
                        'meta' => [
                            'resource_snapshot' => $resource,
                            'transaction_ids' => $group->pluck('id')->all(),
                            'period_start' => $windowStart,
                            'period_end' => $windowEnd,
                        ],
                    ]);
                });

            return $invoice->load('items');
        });
    }

    private function numberFor(Customer $customer, CarbonInterface $periodStart): string
    {
        return sprintf('INV-%s-%04d', $periodStart->format('Ym'), $customer->id);
    }
}
