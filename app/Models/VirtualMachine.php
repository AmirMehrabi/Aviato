<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'customer_id',
    'proxmox_server_id',
    'vm_bundle_id',
    'vmid',
    'name',
    'hostname',
    'node',
    'storage',
    'os_template',
    'ip_address',
    'cpu_cores',
    'ram_gb',
    'disk_gb',
    'ip_count',
    'status',
    'provisioning_status',
    'desired_state',
    'remote_state',
    'last_seen_at',
    'last_started_at',
    'last_stopped_at',
    'last_billed_at',
    'unbilled_amount',
])]
class VirtualMachine extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_SUSPENDED = 'suspended';

    public const PROVISION_PENDING = 'pending';

    public const PROVISION_READY = 'ready';

    public const PROVISION_FAILED = 'failed';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function proxmoxServer(): BelongsTo
    {
        return $this->belongsTo(ProxmoxServer::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'vm_bundle_id');
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function desiredStateSnapshot(): array
    {
        return [
            'name' => $this->name,
            'hostname' => $this->hostname,
            'node' => $this->node,
            'storage' => $this->storage,
            'os_template' => $this->os_template,
            'cpu_cores' => $this->cpu_cores,
            'ram_gb' => $this->ram_gb,
            'disk_gb' => $this->disk_gb,
            'ip_count' => $this->ip_count,
            'ip_address' => $this->ip_address,
            'status' => $this->status,
        ];
    }

    protected function casts(): array
    {
        return [
            'vmid' => 'integer',
            'cpu_cores' => 'integer',
            'ram_gb' => 'integer',
            'disk_gb' => 'integer',
            'ip_count' => 'integer',
            'desired_state' => 'array',
            'remote_state' => 'array',
            'last_seen_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_stopped_at' => 'datetime',
            'last_billed_at' => 'datetime',
            'unbilled_amount' => 'integer',
        ];
    }
}
