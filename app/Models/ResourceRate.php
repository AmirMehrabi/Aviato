<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['resource', 'label', 'unit', 'hourly_price', 'monthly_price', 'billing_policy', 'is_active'])]
class ResourceRate extends Model
{
    public const CPU = 'cpu_core';

    public const RAM = 'ram_gb';

    public const DISK = 'disk_gb';

    public const IP = 'ip_address';

    public const BACKUP = 'backup_gb';

    public const POLICY_RUNNING = 'running';

    public const POLICY_ALWAYS = 'always';

    public static function defaults(): array
    {
        return [
            self::CPU => ['label' => 'vCPU Core', 'unit' => 'core', 'monthly_price' => 120000, 'billing_policy' => self::POLICY_RUNNING],
            self::RAM => ['label' => 'RAM', 'unit' => 'GB', 'monthly_price' => 90000, 'billing_policy' => self::POLICY_RUNNING],
            self::DISK => ['label' => 'SSD Disk', 'unit' => 'GB', 'monthly_price' => 9000, 'billing_policy' => self::POLICY_ALWAYS],
            self::IP => ['label' => 'IP Address', 'unit' => 'IP', 'monthly_price' => 150000, 'billing_policy' => self::POLICY_ALWAYS],
            self::BACKUP => ['label' => 'Backup Storage', 'unit' => 'GB', 'monthly_price' => 6000, 'billing_policy' => self::POLICY_ALWAYS],
        ];
    }

    public static function hoursPerMonth(): int
    {
        return 730;
    }

    protected function casts(): array
    {
        return [
            'hourly_price' => 'decimal:6',
            'monthly_price' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
