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
        $remaining = $limit <= 0 ? null : max(0, $limit - $used);
        $canCreate = $limit <= 0 || $used < $limit;

        return [
            'verified' => $verified,
            'limit' => $limit,
            'active_count' => $activeCount,
            'cooldown_count' => $cooldownCount,
            'used' => $used,
            'remaining' => $remaining,
            'cooldown_days' => $cooldownDays,
            'can_create' => $canCreate,
            'message' => $canCreate ? null : $this->blockedMessage($verified, $limit, $cooldownCount, $cooldownDays),
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

    private function blockedMessage(bool $verified, int $limit, int $cooldownCount, int $cooldownDays): string
    {
        if ($verified) {
            return 'تعداد ماشین های این حساب به سقف مجاز رسیده است.';
        }

        if ($cooldownCount > 0) {
            return 'سقف ساخت ماشین برای حساب تایید نشده پر شده است. ماشین های حذف شده تا '.$cooldownDays.' روز همچنان در سهمیه حساب محاسبه می شوند.';
        }

        return 'حساب های تایید نشده حداکثر می توانند '.$limit.' ماشین بسازند. برای افزایش سقف، کد ملی را در پروفایل تایید کنید.';
    }
}
