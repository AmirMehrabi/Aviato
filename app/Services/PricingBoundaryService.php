<?php

namespace App\Services;

use App\Models\VirtualMachine;
use App\Models\VmBackup;
use App\Models\VmBundle;
use App\Models\VmDisk;
use App\Models\VmUpgradeOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class PricingBoundaryService
{
    public function bundle(VmBundle $bundle, array $data): void
    {
        DB::transaction(function () use ($bundle, $data): void {
            if (VmUpgradeOrder::query()
                ->whereIn('status', ['pending', 'applying', 'reconciliation_required'])
                ->where(fn ($q) => $q->where('from_bundle_id', $bundle->id)->orWhere('to_bundle_id', $bundle->id))->exists()) {
                throw ValidationException::withMessages(['bundle' => 'ابتدا منتظر پایان ارتقاهای وابسته به این پلن بمانید.']);
            }
            $machines = $bundle->virtualMachines()->notDeleted()->orderBy('id')->lockForUpdate()->get();
            $at = now();
            foreach ($machines as $vm) {
                app(UsageBillingService::class)->accrueVm($vm, $at);
            }
            $bundle->update($data);
            app(PortalCache::class)->invalidate();
        });
    }

    public function resources(callable $mutation): void
    {
        DB::transaction(function () use ($mutation): void {
            // Keep all affected cursors stable until the old rates are accrued and the new rates are committed.
            VirtualMachine::query()->notDeleted()->orderBy('id')->lockForUpdate()->get(['id']);
            VmBackup::query()->where('status', VmBackup::STATUS_READY)->orderBy('id')->lockForUpdate()->get(['id']);
            VmDisk::query()->where('status', VmDisk::STATUS_READY)->orderBy('id')->lockForUpdate()->get(['id']);
            app(UsageBillingService::class)->accrueAllDueUsage(now());
            $mutation();
            app(PortalCache::class)->invalidate();
        });
    }
}
