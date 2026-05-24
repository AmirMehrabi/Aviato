<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\VirtualMachine;
use App\Models\WalletTransaction;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;

class UsageBillingService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly WalletService $wallets,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function estimateVmUsage(VirtualMachine $vm, ?CarbonInterface $until = null): array
    {
        $until ??= now();
        $from = $vm->last_billed_at ?? $vm->created_at ?? $until;
        $hours = max(0, $from->floatDiffInHours($until));
        $hourly = $vm->isRunning()
            ? $this->billing->hourlyWhenRunning($vm)
            : $this->billing->persistentHourly($vm);
        $baseAmount = (int) ($vm->unbilled_amount ?? 0);
        $computedAmount = (int) round($hours * $hourly);

        return [
            'from' => $from,
            'until' => $until,
            'hours' => $hours,
            'hourly_rate' => $hourly,
            'amount' => max(0, $baseAmount + $computedAmount),
            'status' => $vm->status,
            'is_running' => $vm->isRunning(),
        ];
    }

    public function chargeVm(VirtualMachine $vm, ?CarbonInterface $until = null): ?WalletTransaction
    {
        $vm->loadMissing(['customer', 'bundle']);
        $usage = $this->estimateVmUsage($vm, $until);

        if ($usage['amount'] <= 0) {
            return null;
        }

        $transaction = $this->wallets->charge(
            $vm->customer,
            $usage['amount'],
            'کسر کارکرد PAYG برای VM '.$vm->name,
            metadata: [
                'category' => 'payg_usage',
                'vm_id' => $vm->id,
                'vm_name' => $vm->name,
                'bundle_id' => $vm->vm_bundle_id,
                'period_start' => $usage['from']->toIso8601String(),
                'period_end' => $usage['until']->toIso8601String(),
                'hours' => round($usage['hours'], 4),
                'hourly_rate' => $usage['hourly_rate'],
                'resource_snapshot' => [
                    'cpu_cores' => $vm->cpu_cores,
                    'ram_gb' => $vm->ram_gb,
                    'disk_gb' => $vm->disk_gb,
                    'ip_count' => $vm->ip_count,
                    'status' => $vm->status,
                    'bundle_name' => $vm->bundle?->name,
                ],
            ],
        );

        $vm->forceFill([
            'last_billed_at' => $usage['until'],
            'unbilled_amount' => 0,
        ])->save();

        return $transaction;
    }

    /**
     * @return Collection<int, WalletTransaction>
     */
    public function chargeAllDueUsage(?CarbonInterface $until = null): Collection
    {
        $transactions = new Collection;
        $until ??= now();

        VirtualMachine::query()
            ->with(['customer', 'bundle'])
            ->whereNotNull('customer_id')
            ->orderBy('customer_id')
            ->chunk(100, function ($vms) use (&$transactions, $until): void {
                foreach ($vms as $vm) {
                    $transaction = $this->chargeVm($vm, $until);

                    if ($transaction) {
                        $transactions->push($transaction);
                    }
                }
            });

        return $transactions;
    }

    public function customerPendingUsage(Customer $customer): int
    {
        return $customer->virtualMachines
            ->sum(fn (VirtualMachine $vm): int => $this->estimateVmUsage($vm)['amount']);
    }
}
