<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'proxmox_server_id',
    'name',
    'node',
    'network_bridge',
    'gateway',
    'prefix_length',
    'nameservers',
    'start_ip',
    'end_ip',
    'is_active',
])]
class IpPool extends Model
{
    public function proxmoxServer(): BelongsTo
    {
        return $this->belongsTo(ProxmoxServer::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(IpAddress::class);
    }

    protected function casts(): array
    {
        return [
            'prefix_length' => 'integer',
            'is_active' => 'boolean',
        ];
    }
}
