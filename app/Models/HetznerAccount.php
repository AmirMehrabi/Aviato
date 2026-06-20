<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'name',
    'api_token',
    'is_active',
    'maintenance_mode',
    'remote_inventory',
    'connection_status',
    'sync_status',
    'sync_error',
    'synced_at',
    'last_seen_at',
])]
#[Hidden(['api_token'])]
class HetznerAccount extends Model
{
    public const CONNECTION_ONLINE = 'online';

    public const CONNECTION_OFFLINE = 'offline';

    public const CONNECTION_UNKNOWN = 'unknown';

    public const SYNC_SYNCED = 'synced';

    public const SYNC_PENDING = 'pending';

    public const SYNC_FAILED = 'failed';

    public function locations(): HasMany
    {
        return $this->hasMany(InfrastructureLocation::class);
    }

    public function hetznerLocations(): HasMany
    {
        return $this->hasMany(HetznerLocation::class);
    }

    public function images(): HasMany
    {
        return $this->hasMany(HetznerImage::class);
    }

    public function serverTypes(): HasMany
    {
        return $this->hasMany(HetznerServerType::class);
    }

    protected function casts(): array
    {
        return [
            'api_token' => 'encrypted',
            'is_active' => 'boolean',
            'maintenance_mode' => 'boolean',
            'remote_inventory' => 'array',
            'synced_at' => 'datetime',
            'last_seen_at' => 'datetime',
        ];
    }
}
