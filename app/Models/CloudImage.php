<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'proxmox_server_id',
    'name',
    'slug',
    'description',
    'os_family',
    'os_version',
    'logo_key',
    'node',
    'template_vmid',
    'default_username',
    'storage',
    'disk_device',
    'network_bridge',
    'ostype',
    'cloud_init_enabled',
    'min_cpu_cores',
    'min_ram_gb',
    'min_disk_gb',
    'is_active',
    'sort_order',
])]
class CloudImage extends Model
{
    protected static function booted(): void
    {
        static::saving(function (CloudImage $image): void {
            if (blank($image->slug)) {
                $image->slug = Str::slug($image->name);
            }
        });
    }

    public function proxmoxServer(): BelongsTo
    {
        return $this->belongsTo(ProxmoxServer::class);
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function allowedBundles(): BelongsToMany
    {
        return $this->belongsToMany(VmBundle::class)
            ->orderBy('sort_order')
            ->orderBy('monthly_price')
            ->orderBy('name');
    }

    protected function casts(): array
    {
        return [
            'template_vmid' => 'integer',
            'min_cpu_cores' => 'integer',
            'min_ram_gb' => 'integer',
            'min_disk_gb' => 'integer',
            'cloud_init_enabled' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
