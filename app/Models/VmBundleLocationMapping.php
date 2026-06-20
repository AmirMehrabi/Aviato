<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'vm_bundle_id',
    'infrastructure_location_id',
    'hetzner_server_type_id',
    'is_active',
    'monthly_price_usd',
    'monthly_price_irr',
    'usd_to_irr_rate',
    'metadata',
    'price_synced_at',
])]
class VmBundleLocationMapping extends Model
{
    public function bundle(): BelongsTo
    {
        return $this->belongsTo(VmBundle::class, 'vm_bundle_id');
    }

    public function location(): BelongsTo
    {
        return $this->belongsTo(InfrastructureLocation::class, 'infrastructure_location_id');
    }

    public function hetznerServerType(): BelongsTo
    {
        return $this->belongsTo(HetznerServerType::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'monthly_price_usd' => 'decimal:4',
            'monthly_price_irr' => 'integer',
            'usd_to_irr_rate' => 'integer',
            'metadata' => 'array',
            'price_synced_at' => 'datetime',
        ];
    }
}
