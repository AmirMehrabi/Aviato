<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hetzner_account_id',
    'infrastructure_location_id',
    'remote_id',
    'name',
    'description',
    'city',
    'country',
    'network_zone',
    'is_active',
    'raw',
    'last_synced_at',
])]
class HetznerLocation extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(HetznerAccount::class, 'hetzner_account_id');
    }

    public function infrastructureLocation(): BelongsTo
    {
        return $this->belongsTo(InfrastructureLocation::class);
    }

    protected function casts(): array
    {
        return [
            'remote_id' => 'integer',
            'is_active' => 'boolean',
            'raw' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
