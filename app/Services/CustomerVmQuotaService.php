<?php

namespace App\Services;

use App\Models\Customer;
use App\Models\Project;
use App\Models\VirtualMachine;

class CustomerVmQuotaService
{
    /**
     * @return array{verified: bool, limit: int, active_count: int, cooldown_count: int, used: int, remaining: int|null, cooldown_days: int, can_create: bool, blocking_code: string|null, message: string|null}
     */
    public function snapshot(Customer $customer, ?Project $project = null): array
    {
        $verified = $customer->hasVerifiedNationalCode();
        $settings = app(WorkspaceProtectionSettings::class);
        $limitKey = $verified ? 'verified_customer_vm_limit' : 'unverified_customer_vm_limit';
        $limit = (int) $settings->value($project, $limitKey);
        $cooldownDays = (int) $settings->value($project, 'deleted_vm_cooldown_days');
        // Inherited limits retain the existing account-wide quota semantics.
        $scopedProject = $settings->hasOverride($project, $limitKey) ? $project : null;
        $machines = $scopedProject ? $scopedProject->virtualMachines() : $customer->virtualMachines();
        $activeCount = $machines->notDeleted()->count();
        $cooldownCount = $verified ? 0 : $this->cooldownDeletedCount($customer, $cooldownDays, $scopedProject);
        $used = $activeCount + $cooldownCount;
        $remaining = $limit <= 0 ? ($verified ? null : 0) : max(0, $limit - $used);
        $canCreate = $verified
            ? ($limit <= 0 || $used < $limit)
            : ($limit > 0 && $used < $limit);
        $blockingCode = $canCreate
            ? null
            : ($scopedProject ? 'workspace_limit_reached' : ($verified ? 'verified_limit_reached' : 'owner_verification_required'));

        return [
            'verified' => $verified,
            'limit' => $limit,
            'active_count' => $activeCount,
            'cooldown_count' => $cooldownCount,
            'used' => $used,
            'remaining' => $remaining,
            'cooldown_days' => $cooldownDays,
            'can_create' => $canCreate,
            'blocking_code' => $blockingCode,
            'message' => $canCreate ? null : ($scopedProject ? 'سقف ساخت ماشین مجازی این فضای کاری تکمیل شده است.' : $this->blockedMessage($verified)),
        ];
    }

    private function cooldownDeletedCount(Customer $customer, int $cooldownDays, ?Project $project = null): int
    {
        if ($cooldownDays <= 0) {
            return 0;
        }

        return ($project ? $project->virtualMachines() : $customer->virtualMachines())
            ->where(function ($query): void {
                $query->where('status', VirtualMachine::STATUS_DELETED)
                    ->orWhereNotNull('deleted_at');
            })
            ->where('deleted_at', '>=', now()->subDays($cooldownDays))
            ->count();
    }

    private function blockedMessage(bool $verified): string
    {
        if ($verified) {
            return 'سقف ساخت ماشین مجازی برای مالک فضای کاری تکمیل شده است.';
        }

        return 'تأیید کد ملی مالک فضای کاری برای ساخت ماشین مجازی بیشتر لازم است.';
    }
}
