<?php

namespace App\Services;

use App\Models\AppSetting;
use App\Models\Project;

class WorkspaceProtectionSettings
{
    public function defaults(): array
    {
        return [
            'unverified_customer_vm_limit' => AppSetting::unverifiedCustomerVmLimit(),
            'verified_customer_vm_limit' => AppSetting::verifiedCustomerVmLimit(),
            'deleted_vm_cooldown_days' => AppSetting::deletedVmCooldownDays(),
            'wallet_retention_days' => AppSetting::customerWalletRetentionDays(),
            'wallet_shutdown_percentage' => AppSetting::customerWalletShutdownPercentage(),
            'vm_rebuild_fee_multiplier_percentage' => AppSetting::vmRebuildFeeMultiplierPercentage(),
        ];
    }

    public function value(?Project $project, string $key): int|float
    {
        return $project?->protection_overrides[$key] ?? $this->defaults()[$key];
    }

    public function hasOverride(?Project $project, string $key): bool
    {
        return isset($project?->protection_overrides[$key]);
    }

    public function rebuildFee(?Project $project, int $monthlyPrice): int
    {
        return (int) round(AppSetting::vmCreationChargeAmount($monthlyPrice)
            * $this->value($project, 'vm_rebuild_fee_multiplier_percentage') / 100);
    }
}
