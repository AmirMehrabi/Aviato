<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'virtual_machine_id',
    'vm_backup_policy_id',
    'source',
    'status',
    'proxmox_task_id',
    'node',
    'storage',
    'volid',
    'filename',
    'size_bytes',
    'started_at',
    'finished_at',
    'last_billed_at',
    'deleted_at',
    'error',
    'remote_state',
])]
class VmBackup extends Model
{
    public const SOURCE_MANUAL = 'manual';

    public const SOURCE_POLICY = 'policy';

    public const STATUS_QUEUED = 'queued';

    public const STATUS_RUNNING = 'running';

    public const STATUS_READY = 'ready';

    public const STATUS_FAILED = 'failed';

    public const STATUS_DELETED = 'deleted';

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function policy(): BelongsTo
    {
        return $this->belongsTo(VmBackupPolicy::class, 'vm_backup_policy_id');
    }

    public function sizeGb(): float
    {
        return $this->size_bytes / 1024 / 1024 / 1024;
    }

    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
            'started_at' => 'datetime',
            'finished_at' => 'datetime',
            'last_billed_at' => 'datetime',
            'deleted_at' => 'datetime',
            'remote_state' => 'array',
        ];
    }
}
