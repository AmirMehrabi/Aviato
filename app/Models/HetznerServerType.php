<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'hetzner_account_id',
    'remote_id',
    'name',
    'description',
    'architecture',
    'cpu_cores',
    'memory_gb',
    'disk_gb',
    'prices',
    'available_locations',
    'deprecated',
    'is_active',
    'raw',
    'last_synced_at',
])]
class HetznerServerType extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(HetznerAccount::class, 'hetzner_account_id');
    }

    public function bundleMappings(): HasMany
    {
        return $this->hasMany(VmBundleLocationMapping::class);
    }

    public function monthlyUsdForLocation(?string $locationName): ?float
    {
        $prices = collect($this->prices ?? []);
        $price = $prices->first(fn (array $price): bool => $locationName === null || ($price['location'] ?? null) === $locationName)
            ?? $prices->first();

        $value = $price['price_monthly']['gross'] ?? $price['price_monthly']['net'] ?? null;

        return $value === null ? null : (float) $value;
    }

    protected function casts(): array
    {
        return [
            'remote_id' => 'integer',
            'cpu_cores' => 'integer',
            'memory_gb' => 'decimal:2',
            'disk_gb' => 'integer',
            'prices' => 'array',
            'available_locations' => 'array',
            'deprecated' => 'boolean',
            'is_active' => 'boolean',
            'raw' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
