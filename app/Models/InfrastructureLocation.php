<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable([
    'provider',
    'proxmox_server_id',
    'hetzner_account_id',
    'name',
    'slug',
    'region',
    'city',
    'country',
    'remote_id',
    'remote_name',
    'is_active',
    'maintenance_mode',
    'sort_order',
    'metadata',
    'last_synced_at',
])]
class InfrastructureLocation extends Model
{
    public const PROVIDER_PROXMOX = 'proxmox';

    public const PROVIDER_HETZNER = 'hetzner';

    public const COUNTRIES = [
        'iran' => 'Iran',
        'usa' => 'USA',
        'singapour' => 'Singapore',
        'germany' => 'Germany',
        'france' => 'France',
    ];

    protected static function booted(): void
    {
        static::saving(function (InfrastructureLocation $location): void {
            if (blank($location->slug)) {
                $location->slug = Str::slug($location->provider.'-'.$location->name.'-'.$location->remote_id);
            }
        });
    }

    public function proxmoxServer(): BelongsTo
    {
        return $this->belongsTo(ProxmoxServer::class);
    }

    public function hetznerAccount(): BelongsTo
    {
        return $this->belongsTo(HetznerAccount::class);
    }

    public function cloudImages(): HasMany
    {
        return $this->hasMany(CloudImage::class);
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function bundleMappings(): HasMany
    {
        return $this->hasMany(VmBundleLocationMapping::class);
    }

    public function isHetzner(): bool
    {
        return $this->provider === self::PROVIDER_HETZNER;
    }

    public function isProxmox(): bool
    {
        return $this->provider === self::PROVIDER_PROXMOX;
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'maintenance_mode' => 'boolean',
            'sort_order' => 'integer',
            'metadata' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
