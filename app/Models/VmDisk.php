<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'virtual_machine_id',
    'vm_upgrade_order_id',
    'disk_device',
    'storage',
    'size_gb',
    'status',
    'last_billed_at',
    'remote_state',
])]
class VmDisk extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function upgradeOrder(): BelongsTo
    {
        return $this->belongsTo(VmUpgradeOrder::class, 'vm_upgrade_order_id');
    }

    protected function casts(): array
    {
        return [
            'size_gb' => 'integer',
            'last_billed_at' => 'datetime',
            'remote_state' => 'array',
        ];
    }
}
