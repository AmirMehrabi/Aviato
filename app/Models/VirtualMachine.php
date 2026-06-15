<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable([
    'customer_id',
    'project_id',
    'created_by_customer_id',
    'uuid',
    'proxmox_server_id',
    'vm_bundle_id',
    'cloud_image_id',
    'ip_address_id',
    'vmid',
    'template_vmid',
    'name',
    'display_name',
    'hostname',
    'node',
    'storage',
    'os_template',
    'iso_volume',
    'network_bridge',
    'mac_address',
    'ip_address',
    'login_username',
    'login_password',
    'ssh_public_key',
    'cpu_cores',
    'ram_gb',
    'disk_gb',
    'ip_count',
    'status',
    'provisioning_status',
    'desired_state',
    'remote_state',
    'provisioning_job_id',
    'provisioning_task_id',
    'last_seen_at',
    'last_started_at',
    'last_stopped_at',
    'delete_requested_at',
    'delete_started_at',
    'deleted_at',
    'delete_failed_at',
    'delete_error',
    'delete_task_id',
    'last_billed_at',
    'unbilled_amount',
])]
class VirtualMachine extends Model
{
    public const STATUS_RUNNING = 'running';

    public const STATUS_STOPPED = 'stopped';

    public const STATUS_SUSPENDED = 'suspended';

    public const STATUS_DELETING = 'deleting';

    public const STATUS_DELETED = 'deleted';

    public const PROVISION_PENDING = 'pending';

    public const PROVISION_READY = 'ready';

    public const PROVISION_FAILED = 'failed';

    protected static function booted(): void
    {
        static::creating(function (VirtualMachine $virtualMachine): void {
            $virtualMachine->uuid ??= (string) Str::uuid();

            if (! $virtualMachine->project_id && $virtualMachine->customer_id) {
                $project = Customer::query()->find($virtualMachine->customer_id)?->ensureDefaultProject();
                $virtualMachine->project_id = $project?->id;
            }

            if (! $virtualMachine->created_by_customer_id && $virtualMachine->customer_id) {
                $virtualMachine->created_by_customer_id = $virtualMachine->customer_id;
            }
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function getDisplayNameAttribute(): string
    {
        $value = $this->attributes['display_name'] ?? null;

        return $value !== null && $value !== '' ? $value : $this->name;
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'created_by_customer_id');
    }

    public function proxmoxServer(): BelongsTo
    {
        return $this->belongsTo(ProxmoxServer::class);
    }

    public function bundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'vm_bundle_id');
    }

    public function cloudImage(): BelongsTo
    {
        return $this->belongsTo(CloudImage::class);
    }

    public function reservedIpAddress(): BelongsTo
    {
        return $this->belongsTo(IpAddress::class, 'ip_address_id');
    }

    public function backupPolicy(): HasOne
    {
        return $this->hasOne(VmBackupPolicy::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(VmBackup::class);
    }

    public function upgradeOrders(): HasMany
    {
        return $this->hasMany(VmUpgradeOrder::class)->latest();
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    public function disks(): HasMany
    {
        return $this->hasMany(VmDisk::class);
    }

    public function transfers(): HasMany
    {
        return $this->hasMany(VmTransfer::class);
    }

    public function pendingUpgradeOrders(): HasMany
    {
        return $this->hasMany(VmUpgradeOrder::class)
            ->whereIn('status', [VmUpgradeOrder::STATUS_PENDING, VmUpgradeOrder::STATUS_APPLYING]);
    }

    public function isRunning(): bool
    {
        return $this->status === self::STATUS_RUNNING;
    }

    public function isDeleting(): bool
    {
        return $this->status === self::STATUS_DELETING;
    }

    public function isDeleted(): bool
    {
        return $this->status === self::STATUS_DELETED || $this->deleted_at !== null;
    }

    public function isActionLocked(): bool
    {
        return in_array($this->status, [self::STATUS_DELETING, self::STATUS_DELETED], true);
    }

    public function deleteAttemptIsStale(int $minutes = 15): bool
    {
        if (! $this->isDeleting() || $this->delete_failed_at) {
            return false;
        }

        $lastDeleteActivity = $this->delete_started_at
            ?? $this->delete_requested_at
            ?? $this->updated_at;

        return $lastDeleteActivity === null || $lastDeleteActivity->lte(now()->subMinutes($minutes));
    }

    /**
     * @param  Builder<VirtualMachine>  $query
     * @return Builder<VirtualMachine>
     */
    public function scopeNotDeleted(Builder $query): Builder
    {
        return $query
            ->whereNull('deleted_at')
            ->where('status', '!=', self::STATUS_DELETED);
    }

    public function desiredStateSnapshot(): array
    {
        return [
            'name' => $this->name,
            'hostname' => $this->hostname,
            'node' => $this->node,
            'storage' => $this->storage,
            'os_template' => $this->os_template,
            'iso_volume' => $this->iso_volume,
            'cloud_image_id' => $this->cloud_image_id,
            'template_vmid' => $this->template_vmid,
            'network_bridge' => $this->network_bridge,
            'mac_address' => $this->mac_address,
            'cpu_cores' => $this->cpu_cores,
            'ram_gb' => $this->ram_gb,
            'disk_gb' => $this->disk_gb,
            'ip_count' => $this->ip_count,
            'project_id' => $this->project_id,
            'customer_id' => $this->customer_id,
            'created_by_customer_id' => $this->created_by_customer_id,
            'ip_address' => $this->ip_address,
            'login_username' => $this->login_username,
            'ssh_public_key' => filled($this->ssh_public_key),
            'status' => $this->status,
        ];
    }

    protected function casts(): array
    {
        return [
            'vmid' => 'integer',
            'template_vmid' => 'integer',
            'cpu_cores' => 'integer',
            'ram_gb' => 'integer',
            'disk_gb' => 'integer',
            'ip_count' => 'integer',
            'desired_state' => 'array',
            'remote_state' => 'array',
            'login_password' => 'encrypted',
            'last_seen_at' => 'datetime',
            'last_started_at' => 'datetime',
            'last_stopped_at' => 'datetime',
            'delete_requested_at' => 'datetime',
            'delete_started_at' => 'datetime',
            'deleted_at' => 'datetime',
            'delete_failed_at' => 'datetime',
            'last_billed_at' => 'datetime',
            'unbilled_amount' => 'integer',
        ];
    }
}
