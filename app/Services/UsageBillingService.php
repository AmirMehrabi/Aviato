<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\UsageAccrual;
use App\Models\UsageSettlement;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmDisk;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class UsageBillingService
{
    public function __construct(
        private readonly BillingService $billing,
        private readonly WalletService $wallets,
        private readonly UsageBalanceService $usageBalances,
        private readonly ?ResellerService $resellerService = null,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function estimateVmUsage(VirtualMachine $vm, ?CarbonInterface $until = null): array
    {
        $until ??= now();
        $from = $vm->last_billed_at ?? $vm->created_at ?? $until;

        if ($vm->isActionLocked() || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
            return [
                'from' => $from,
                'until' => $until,
                'hours' => 0,
                'hourly_rate' => 0,
                'amount' => 0,
                'status' => $vm->status,
                'is_running' => false,
            ];
        }

        $seconds = max(0, $from->diffInSeconds($until));
        $hourly = $vm->isRunning()
            ? $this->billing->hourlyWhenRunning($vm)
            : $this->billing->persistentHourly($vm);

        return [
            'from' => $from,
            'until' => $until,
            'hours' => $seconds / 3600,
            'hourly_rate' => $hourly,
            'amount' => max(0, (int) ($vm->unbilled_amount ?? 0) + (int) round($seconds * $hourly / 3600)),
            'status' => $vm->status,
            'is_running' => $vm->isRunning(),
        ];
    }

    public function accrueVm(VirtualMachine $vm, ?CarbonInterface $until = null): ?UsageAccrual
    {
        return DB::transaction(function () use ($vm, $until): ?UsageAccrual {
            $locked = VirtualMachine::query()
                ->with(['customer', 'bundle', 'project.owner', 'creator'])
                ->whereKey($vm->id)
                ->lockForUpdate()
                ->firstOrFail();
            $until ??= now();
            $usage = $this->estimateVmUsage($locked, $until);
            $billingCustomer = $locked->project?->owner ?? $locked->customer;

            if (! $billingCustomer) {
                return null;
            }

            if (($usage['hours'] <= 0 || $usage['hourly_rate'] <= 0) && (int) ($locked->unbilled_amount ?? 0) <= 0) {
                $locked->forceFill(['last_billed_at' => $until])->save();

                return null;
            }

            $accrual = $this->accrueInterval(
                customer: $billingCustomer,
                projectId: $locked->project_id,
                category: UsageAccrual::CATEGORY_VM,
                resourceType: 'virtual_machine',
                resourceId: $locked->id,
                virtualMachineId: $locked->id,
                resourceName: $locked->name,
                from: $usage['from'],
                until: $usage['until'],
                hourlyRate: (float) $usage['hourly_rate'],
                snapshot: [
                    'project_id' => $locked->project_id,
                    'project_name' => $locked->project?->name,
                    'project_owner_id' => $billingCustomer->id,
                    'created_by_customer_id' => $locked->created_by_customer_id,
                    'bundle_id' => $locked->vm_bundle_id,
                    'cpu_cores' => $locked->cpu_cores,
                    'ram_gb' => $locked->ram_gb,
                    'disk_gb' => $locked->disk_gb,
                    'ip_count' => $locked->ip_count,
                    'tax_exempt' => $locked->tax_exempt,
                    'status' => $locked->status,
                    'bundle_name' => $locked->bundle?->name,
                    'provider' => $locked->provider ?: VirtualMachine::PROVIDER_PROXMOX,
                    'provider_price_snapshot' => $locked->isHetzner() ? $this->billing->hetznerPriceSnapshot($locked) : null,
                ],
                carryAmount: (int) ($locked->unbilled_amount ?? 0),
            );

            $locked->forceFill([
                'last_billed_at' => $until,
                'unbilled_amount' => 0,
            ])->save();

            return $accrual;
        });
    }

    public function accrueBackup(VmBackup $backup, ?CarbonInterface $until = null): ?UsageAccrual
    {
        return DB::transaction(function () use ($backup, $until): ?UsageAccrual {
            $locked = VmBackup::query()
                ->with('virtualMachine.project.owner', 'virtualMachine.customer')
                ->whereKey($backup->id)
                ->lockForUpdate()
                ->firstOrFail();
            $until ??= now();
            $vm = $locked->virtualMachine;
            $billingCustomer = $vm?->project?->owner ?? $vm?->customer;
            $from = $locked->last_billed_at ?? $locked->finished_at ?? $locked->created_at ?? $until;

            if (! $billingCustomer || ! $vm || $locked->status !== VmBackup::STATUS_READY || $locked->size_bytes <= 0) {
                return null;
            }

            $accrual = $this->accrueInterval(
                customer: $billingCustomer,
                projectId: $vm->project_id,
                category: UsageAccrual::CATEGORY_BACKUP,
                resourceType: 'vm_backup',
                resourceId: $locked->id,
                virtualMachineId: $vm->id,
                resourceName: $vm->name,
                from: $from,
                until: $until,
                hourlyRate: $this->billing->backupHourly($locked),
                snapshot: [
                    'project_id' => $vm->project_id,
                    'project_name' => $vm->project?->name,
                    'project_owner_id' => $billingCustomer->id,
                    'created_by_customer_id' => $vm->created_by_customer_id,
                    'size_gb' => round($locked->sizeGb(), 4),
                    'size_bytes' => $locked->size_bytes,
                    'volid' => $locked->volid,
                    'storage' => $locked->storage,
                ],
            );

            $locked->forceFill(['last_billed_at' => $until])->save();

            return $accrual;
        });
    }

    public function accrueExtraDisk(VmDisk $disk, ?CarbonInterface $until = null): ?UsageAccrual
    {
        return DB::transaction(function () use ($disk, $until): ?UsageAccrual {
            $locked = VmDisk::query()
                ->with('virtualMachine.project.owner', 'virtualMachine.customer')
                ->whereKey($disk->id)
                ->lockForUpdate()
                ->firstOrFail();
            $until ??= now();
            $vm = $locked->virtualMachine;
            $billingCustomer = $vm?->project?->owner ?? $vm?->customer;
            $from = $locked->last_billed_at ?? $locked->created_at ?? $until;

            if (! $billingCustomer || ! $vm || $locked->status !== VmDisk::STATUS_READY) {
                return null;
            }

            $accrual = $this->accrueInterval(
                customer: $billingCustomer,
                projectId: $vm->project_id,
                category: UsageAccrual::CATEGORY_EXTRA_DISK,
                resourceType: 'vm_disk',
                resourceId: $locked->id,
                virtualMachineId: $vm->id,
                resourceName: $vm->name,
                from: $from,
                until: $until,
                hourlyRate: $this->billing->extraDiskHourly($locked),
                snapshot: [
                    'project_id' => $vm->project_id,
                    'project_name' => $vm->project?->name,
                    'project_owner_id' => $billingCustomer->id,
                    'created_by_customer_id' => $vm->created_by_customer_id,
                    'disk_device' => $locked->disk_device,
                    'size_gb' => $locked->size_gb,
                    'storage' => $locked->storage,
                ],
            );

            $locked->forceFill(['last_billed_at' => $until])->save();

            return $accrual;
        });
    }

    /**
     * @return Collection<int, UsageAccrual>
     */
    public function accrueAllDueUsage(?CarbonInterface $until = null): Collection
    {
        $accruals = new Collection;
        $until ??= now();

        VirtualMachine::query()
            ->notDeleted()
            ->whereNotIn('status', [VirtualMachine::STATUS_DELETING, VirtualMachine::STATUS_DELETED])
            ->orderBy('id')
            ->chunkById(100, function ($vms) use ($accruals, $until): void {
                foreach ($vms as $vm) {
                    if ($accrual = $this->accrueVm($vm, $until)) {
                        $accruals->push($accrual);
                    }
                }
            });

        VmBackup::query()
            ->where('status', VmBackup::STATUS_READY)
            ->where('size_bytes', '>', 0)
            ->orderBy('id')
            ->chunkById(100, function ($backups) use ($accruals, $until): void {
                foreach ($backups as $backup) {
                    if ($accrual = $this->accrueBackup($backup, $until)) {
                        $accruals->push($accrual);
                    }
                }
            });

        VmDisk::query()
            ->where('status', VmDisk::STATUS_READY)
            ->orderBy('id')
            ->chunkById(100, function ($disks) use ($accruals, $until): void {
                foreach ($disks as $disk) {
                    if ($accrual = $this->accrueExtraDisk($disk, $until)) {
                        $accruals->push($accrual);
                    }
                }
            });

        return $accruals;
    }

    /**
     * @return Collection<int, UsageSettlement>
     */
    public function settleDate(CarbonInterface|string $date): Collection
    {
        $serviceDate = CarbonImmutable::parse($date)->toDateString();
        $settlements = new Collection;

        UsageAccrual::query()
            ->whereDate('service_date', $serviceDate)
            ->whereNull('settled_at')
            ->select(['customer_id', 'project_id', 'scope_key'])
            ->distinct()
            ->orderBy('customer_id')
            ->get()
            ->each(function (UsageAccrual $scope) use ($serviceDate, $settlements): void {
                $settlement = DB::transaction(function () use ($scope, $serviceDate): UsageSettlement {
                    $settlement = UsageSettlement::query()->firstOrCreate([
                        'customer_id' => $scope->customer_id,
                        'scope_key' => $scope->scope_key,
                        'service_date' => $serviceDate,
                    ], [
                        'project_id' => $scope->project_id,
                    ]);
                    $settlement = UsageSettlement::query()->whereKey($settlement->id)->lockForUpdate()->firstOrFail();

                    if ($settlement->settled_at) {
                        return $settlement;
                    }

                    $accruals = UsageAccrual::query()
                        ->where('customer_id', $scope->customer_id)
                        ->where('scope_key', $scope->scope_key)
                        ->whereDate('service_date', $serviceDate)
                        ->whereNull('settled_at')
                        ->lockForUpdate()
                        ->get();
                    $amount = (int) $accruals->sum('amount');
                    $settledAt = now();

                    $settlement->forceFill(['amount' => $amount])->save();
                    $accruals->each->forceFill([
                        'usage_settlement_id' => $settlement->id,
                        'settled_at' => $settledAt,
                    ])->each->save();

                    $transaction = $amount > 0
                        ? $this->wallets->charge(
                            $settlement->customer,
                            $amount,
                            'کسر تجمیعی کارکرد PAYG روزانه',
                            reference: $settlement,
                            metadata: [
                                'category' => 'usage_settlement',
                                'service_date' => $serviceDate,
                                'project_id' => $scope->project_id,
                                'resource_count' => $accruals->count(),
                                'categories' => $accruals->groupBy('category')->map->count()->all(),
                            ],
                        )
                        : null;

                    $settlement->forceFill([
                        'wallet_transaction_id' => $transaction?->id,
                        'settled_at' => $settledAt,
                    ])->save();

                    if ($transaction) {
                        ($this->resellerService ?? app(ResellerService::class))
                            ->calculateCommissionForSettlement($settlement->refresh());
                    }

                    return $settlement->refresh();
                });

                $settlements->push($settlement);
            });

        return $settlements;
    }

    public function customerPendingUsage(Customer $customer): int
    {
        return $this->usageBalances->customerPendingUsage($customer);
    }

    public function projectPendingUsage(int $projectId): int
    {
        return $this->usageBalances->projectPendingUsage($projectId);
    }

    // Compatibility aliases for lifecycle callers while they transition to accrual terminology.
    public function chargeVm(VirtualMachine $vm, ?CarbonInterface $until = null): ?UsageAccrual
    {
        return $this->accrueVm($vm, $until);
    }

    public function chargeBackup(VmBackup $backup, ?CarbonInterface $until = null): ?UsageAccrual
    {
        return $this->accrueBackup($backup, $until);
    }

    public function chargeExtraDisk(VmDisk $disk, ?CarbonInterface $until = null): ?UsageAccrual
    {
        return $this->accrueExtraDisk($disk, $until);
    }

    /**
     * @return Collection<int, UsageAccrual>
     */
    public function chargeAllDueUsage(?CarbonInterface $until = null): Collection
    {
        return $this->accrueAllDueUsage($until);
    }

    private function accrueInterval(
        Customer $customer,
        ?int $projectId,
        string $category,
        string $resourceType,
        int $resourceId,
        ?int $virtualMachineId,
        string $resourceName,
        CarbonInterface $from,
        CarbonInterface $until,
        float $hourlyRate,
        array $snapshot,
        int $carryAmount = 0,
    ): ?UsageAccrual {
        $cursor = CarbonImmutable::instance($from->toImmutable());
        $end = CarbonImmutable::instance($until->toImmutable());
        $lastAccrual = null;
        $scopeKey = $projectId ? 'project:'.$projectId : 'customer:'.$customer->id;

        if ($carryAmount > 0 && $cursor->greaterThanOrEqualTo($end)) {
            $end = $cursor->addSecond();
        }

        while ($cursor->lessThan($end)) {
            $segmentEnd = min($cursor->endOfDay()->addSecond(), $end);
            $seconds = max(0, $cursor->diffInSeconds($segmentEnd));
            $serviceDate = $cursor->toDateString();
            $accrual = UsageAccrual::query()->firstOrCreate([
                'customer_id' => $customer->id,
                'scope_key' => $scopeKey,
                'category' => $category,
                'resource_type' => $resourceType,
                'resource_id' => $resourceId,
                'service_date' => $serviceDate,
            ], [
                'project_id' => $projectId,
                'virtual_machine_id' => $virtualMachineId,
                'resource_name' => $resourceName,
                'period_start' => $cursor,
                'period_end' => $segmentEnd,
                'snapshot' => $snapshot,
                'segments' => [],
            ]);
            $accrual = UsageAccrual::query()->whereKey($accrual->id)->lockForUpdate()->firstOrFail();

            if ($accrual->settled_at) {
                throw new \RuntimeException('Cannot append usage to an already settled accrual period.');
            }

            $segments = $accrual->segments ?? [];
            $segment = [
                'period_start' => $cursor->toIso8601String(),
                'period_end' => $segmentEnd->toIso8601String(),
                'seconds' => $seconds,
                'hourly_rate' => $hourlyRate,
                'amount' => (int) round($seconds * $hourlyRate / 3600),
            ];
            $lastIndex = array_key_last($segments);

            if ($lastIndex !== null
                && (float) $segments[$lastIndex]['hourly_rate'] === $hourlyRate
                && ($segments[$lastIndex]['period_end'] ?? null) === $segment['period_start']) {
                $segments[$lastIndex]['period_end'] = $segment['period_end'];
                $segments[$lastIndex]['seconds'] += $seconds;
                $segments[$lastIndex]['amount'] = (int) round($segments[$lastIndex]['seconds'] * $hourlyRate / 3600);
            } else {
                $segments[] = $segment;
            }

            if ($carryAmount > 0) {
                $segments[] = [
                    'period_start' => $cursor->toIso8601String(),
                    'period_end' => $cursor->toIso8601String(),
                    'seconds' => 0,
                    'hourly_rate' => 0,
                    'amount' => $carryAmount,
                    'type' => 'carry',
                ];
                $carryAmount = 0;
            }

            $accrual->forceFill([
                'project_id' => $projectId,
                'virtual_machine_id' => $virtualMachineId,
                'resource_name' => $resourceName,
                'period_start' => min($accrual->period_start, $cursor),
                'period_end' => max($accrual->period_end, $segmentEnd),
                'accrued_seconds' => collect($segments)->sum('seconds'),
                'amount' => collect($segments)->sum('amount'),
                'segments' => $segments,
                'snapshot' => $snapshot,
            ])->save();

            $lastAccrual = $accrual;
            $cursor = $segmentEnd;
        }

        return $lastAccrual;
    }
}
