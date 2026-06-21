<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'project_id',
    'scope_key',
    'category',
    'resource_type',
    'resource_id',
    'virtual_machine_id',
    'resource_name',
    'service_date',
    'period_start',
    'period_end',
    'accrued_seconds',
    'amount',
    'segments',
    'snapshot',
    'usage_settlement_id',
    'settled_at',
])]
class UsageAccrual extends Model
{
    public const CATEGORY_VM = 'payg_usage';

    public const CATEGORY_BACKUP = 'backup_storage';

    public const CATEGORY_EXTRA_DISK = 'extra_disk_storage';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function settlement(): BelongsTo
    {
        return $this->belongsTo(UsageSettlement::class, 'usage_settlement_id');
    }

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'accrued_seconds' => 'integer',
            'amount' => 'integer',
            'segments' => 'array',
            'snapshot' => 'array',
            'settled_at' => 'datetime',
        ];
    }
}
