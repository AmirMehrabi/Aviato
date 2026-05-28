<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable([
    'customer_id',
    'virtual_machine_id',
    'from_bundle_id',
    'to_bundle_id',
    'type',
    'status',
    'before_snapshot',
    'after_snapshot',
    'minimum_wallet_balance',
    'estimated_monthly_delta',
    'proxmox_task_id',
    'failure_reason',
    'applied_at',
])]
class VmUpgradeOrder extends Model
{
    public const TYPE_BUNDLE = 'bundle';

    public const TYPE_PRIMARY_DISK = 'primary_disk';

    public const TYPE_EXTRA_DISK = 'extra_disk';

    public const STATUS_PENDING = 'pending';

    public const STATUS_APPLYING = 'applying';

    public const STATUS_SUCCEEDED = 'succeeded';

    public const STATUS_FAILED = 'failed';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function fromBundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'from_bundle_id');
    }

    public function toBundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'to_bundle_id');
    }

    public function disk(): HasOne
    {
        return $this->hasOne(VmDisk::class);
    }

    public function isPending(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_APPLYING], true);
    }

    protected function casts(): array
    {
        return [
            'before_snapshot' => 'array',
            'after_snapshot' => 'array',
            'minimum_wallet_balance' => 'integer',
            'estimated_monthly_delta' => 'integer',
            'applied_at' => 'datetime',
        ];
    }
}
