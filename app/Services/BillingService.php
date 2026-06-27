<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\ResourceRate;
use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBundleLocationMapping;
use App\Models\VmDisk;
use Illuminate\Support\Collection;
use RuntimeException;

class BillingService
{
    public function estimateMonthly(VirtualMachine $vm): int
    {
        if ($vm->isActionLocked() || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
            return 0;
        }

        return (int) round($this->hourlyWhenRunning($vm) * ResourceRate::hoursPerMonth());
    }

    public function estimateStoppedMonthly(VirtualMachine $vm): int
    {
        if ($vm->isActionLocked() || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
            return 0;
        }

        return (int) round($this->persistentHourly($vm) * ResourceRate::hoursPerMonth());
    }

    public function currentAccrued(VirtualMachine $vm): int
    {
        if ($vm->isActionLocked() || $vm->status === VirtualMachine::STATUS_SUSPENDED) {
            return 0;
        }

        $from = $vm->last_billed_at ?? $vm->created_at ?? now();
        $hours = max(0, $from->floatDiffInHours(now()));
        $hourly = $vm->isRunning() ? $this->hourlyWhenRunning($vm) : $this->persistentHourly($vm);

        return (int) ($vm->unbilled_amount ?? 0) + (int) round($hours * $hourly);
    }

    public function hourlyWhenRunning(VirtualMachine $vm): float
    {
        if ($vm->isHetzner()) {
            return $this->applyTaxIfApplicable($this->hetznerHourly($vm), $vm);
        }

        if ($vm->bundle) {
            return $this->applyTaxIfApplicable((float) $vm->bundle->hourly_price, $vm);
        }

        $rates = $this->rates();
        $base = ($vm->cpu_cores * $this->rate($rates, ResourceRate::CPU))
            + ($vm->ram_gb * $this->rate($rates, ResourceRate::RAM))
            + ($vm->disk_gb * $this->rate($rates, ResourceRate::DISK))
            + ($vm->ip_count * $this->rate($rates, ResourceRate::IP));

        return $this->applyTaxIfApplicable($base, $vm);
    }

    public function persistentHourly(VirtualMachine $vm): float
    {
        if ($vm->isHetzner()) {
            return $this->applyTaxIfApplicable($this->hetznerHourly($vm), $vm);
        }

        $rates = $this->rates();
        $base = ($vm->disk_gb * $this->rate($rates, ResourceRate::DISK))
            + ($vm->ip_count * $this->rate($rates, ResourceRate::IP));

        return $this->applyTaxIfApplicable($base, $vm);
    }

    public function backupHourly(VmBackup $backup): float
    {
        $rates = $this->rates();
        $base = $backup->sizeGb() * $this->rate($rates, ResourceRate::BACKUP);

        return $this->applyTaxIfApplicable($base, $backup->virtualMachine);
    }

    public function diskHourly(int $sizeGb): float
    {
        return $sizeGb * $this->rate($this->rates(), ResourceRate::DISK);
    }

    public function extraDiskHourly(VmDisk $disk): float
    {
        $base = $this->diskHourly($disk->size_gb);

        return $this->applyTaxIfApplicable($base, $disk->virtualMachine);
    }

    public function customerSummary(int $customerId): array
    {
        $vms = VirtualMachine::query()
            ->notDeleted()
            ->with(['bundle', 'disks'])
            ->where('customer_id', $customerId)
            ->get();

        return [
            'running' => $vms->where('status', VirtualMachine::STATUS_RUNNING)->count(),
            'stopped' => $vms->where('status', VirtualMachine::STATUS_STOPPED)->count(),
            'monthly_spend' => $vms
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => ($vm->isRunning() ? $this->estimateMonthly($vm) : $this->estimateStoppedMonthly($vm))
                    + $vm->disks->where('status', VmDisk::STATUS_READY)->sum(fn (VmDisk $disk): int => (int) round($this->extraDiskHourly($disk) * ResourceRate::hoursPerMonth()))),
            'unbilled_accrued' => $vms
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => $this->currentAccrued($vm)),
        ];
    }

    public function projectSummary(int $projectId): array
    {
        $vms = VirtualMachine::query()
            ->notDeleted()
            ->with(['bundle', 'disks'])
            ->where('project_id', $projectId)
            ->get();

        return [
            'running' => $vms->where('status', VirtualMachine::STATUS_RUNNING)->count(),
            'stopped' => $vms->where('status', VirtualMachine::STATUS_STOPPED)->count(),
            'monthly_spend' => $vms
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => ($vm->isRunning() ? $this->estimateMonthly($vm) : $this->estimateStoppedMonthly($vm))
                    + $vm->disks->where('status', VmDisk::STATUS_READY)->sum(fn (VmDisk $disk): int => (int) round($this->extraDiskHourly($disk) * ResourceRate::hoursPerMonth()))),
            'unbilled_accrued' => $vms
                ->reject(fn (VirtualMachine $vm): bool => $vm->isActionLocked())
                ->sum(fn (VirtualMachine $vm): int => $this->currentAccrued($vm)),
        ];
    }

    private function applyTaxIfApplicable(float $hourlyRate, ?VirtualMachine $vm): float
    {
        if (! $vm || $vm->tax_exempt || ! AppSetting::taxEnabled()) {
            return $hourlyRate;
        }

        return $hourlyRate * AppSetting::taxMultiplier();
    }

    /** @return Collection<string, ResourceRate> */
    private function rates(): Collection
    {
        return ResourceRate::query()->where('is_active', true)->get()->keyBy('resource');
    }

    /** @param Collection<string, ResourceRate> $rates */
    private function rate(Collection $rates, string $resource): float
    {
        return (float) ($rates->get($resource)?->hourly_price ?? 0);
    }

    public function hetznerMonthlyPrice(VirtualMachine $vm): int
    {
        $mapping = $this->hetznerMapping($vm);
        $serverType = $mapping?->hetznerServerType;
        $usd = $serverType?->monthlyUsdForLocation($vm->infrastructureLocation?->remote_name)
            ?? (float) ($mapping?->monthly_price_usd ?? 0);

        if ($usd <= 0) {
            $snapshot = (int) data_get($vm->provider_metadata, 'hetzner_price.monthly_price_irr', 0);

            if ($snapshot > 0) {
                return $snapshot;
            }

            throw new RuntimeException('Hetzner price mapping is missing for this VM.');
        }

        $converted = AppSetting::convertHetznerUsdToIrr($usd);

        if ($converted <= 0) {
            throw new RuntimeException('USD to IRR rate is not configured for Hetzner billing.');
        }

        return $converted;
    }

    public function hetznerPriceSnapshot(VirtualMachine $vm): array
    {
        $mapping = $this->hetznerMapping($vm);
        $serverType = $mapping?->hetznerServerType;
        $usd = $serverType?->monthlyUsdForLocation($vm->infrastructureLocation?->remote_name)
            ?? (float) ($mapping?->monthly_price_usd ?? 0);

        return [
            'monthly_price_usd' => $usd,
            'monthly_price_irr' => $usd > 0 ? AppSetting::convertHetznerUsdToIrr($usd) : 0,
            'usd_to_irr_rate' => AppSetting::hetznerUsdToIrrRate(),
            'markup_percentage' => AppSetting::hetznerPriceMarkupPercentage(),
            'server_type' => $serverType?->name,
            'location' => $vm->infrastructureLocation?->remote_name,
        ];
    }

    private function hetznerHourly(VirtualMachine $vm): float
    {
        return $this->hetznerMonthlyPrice($vm) / ResourceRate::hoursPerMonth();
    }

    private function hetznerMapping(VirtualMachine $vm): ?VmBundleLocationMapping
    {
        if (! $vm->vm_bundle_id || ! $vm->infrastructure_location_id) {
            return null;
        }

        return VmBundleLocationMapping::query()
            ->with(['hetznerServerType', 'location'])
            ->where('vm_bundle_id', $vm->vm_bundle_id)
            ->where('infrastructure_location_id', $vm->infrastructure_location_id)
            ->where('is_active', true)
            ->first();
    }
}
