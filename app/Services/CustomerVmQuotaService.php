<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\VirtualMachine;

class CustomerVmQuotaService
{
    /**
     * @return array{verified: bool, limit: int, active_count: int, cooldown_count: int, used: int, remaining: int|null, cooldown_days: int, can_create: bool, message: string|null}
     */
    public function snapshot(Customer $customer): array
    {
        $verified = $customer->hasVerifiedNationalCode();
        $limit = $verified ? AppSetting::verifiedCustomerVmLimit() : AppSetting::unverifiedCustomerVmLimit();
        $cooldownDays = AppSetting::deletedVmCooldownDays();

        $activeCount = $customer->virtualMachines()->notDeleted()->count();
        $cooldownCount = $verified ? 0 : $this->cooldownDeletedCount($customer, $cooldownDays);
        $used = $activeCount + $cooldownCount;
        $remaining = $limit <= 0 ? ($verified ? null : 0) : max(0, $limit - $used);
        $canCreate = $verified
            ? ($limit <= 0 || $used < $limit)
            : ($limit > 0 && $used < $limit);

        return [
            'verified' => $verified,
            'limit' => $limit,
            'active_count' => $activeCount,
            'cooldown_count' => $cooldownCount,
            'used' => $used,
            'remaining' => $remaining,
            'cooldown_days' => $cooldownDays,
            'can_create' => $canCreate,
            'message' => $canCreate ? null : $this->blockedMessage($verified),
        ];
    }

    private function cooldownDeletedCount(Customer $customer, int $cooldownDays): int
    {
        if ($cooldownDays <= 0) {
            return 0;
        }

        return $customer->virtualMachines()
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
            return 'در حال حاضر ظرفیت ساخت ماشین مجازی برای این حساب محدود است و امکان ساخت ماشین جدید وجود ندارد.';
        }

        return 'برای ساخت ماشین مجازی بیشتر، کد ملی‌تان را در پروفایل تایید کنید.';
    }
}
