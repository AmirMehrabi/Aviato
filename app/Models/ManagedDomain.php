<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'uuid', 'customer_id', 'name', 'extension', 'status', 'provider_id', 'registrant', 'nameservers', 'registered_at', 'expires_at', 'synced_at', 'auto_renew', 'auto_renew_limit', 'auto_renew_consented_at'])]
class ManagedDomain extends Model
{
    protected $attributes = ['provider' => 'reseller_world'];

    protected function casts(): array
    {
        return ['registrant' => 'encrypted:array', 'nameservers' => 'array', 'registered_at' => 'datetime', 'expires_at' => 'datetime', 'synced_at' => 'datetime', 'auto_renew' => 'boolean', 'auto_renew_limit' => 'integer', 'auto_renew_consented_at' => 'datetime'];
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DomainOrder::class);
    }
}
