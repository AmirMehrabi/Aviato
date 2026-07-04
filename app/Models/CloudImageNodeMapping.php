<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'cloud_image_id',
    'proxmox_server_id',
    'node',
    'template_vmid',
    'storage',
    'network_bridge',
    'template_version',
    'is_enabled',
    'verified_at',
    'verification_snapshot',
])]
class CloudImageNodeMapping extends Model
{
    public function cloudImage(): BelongsTo
    {
        return $this->belongsTo(CloudImage::class);
    }

    public function proxmoxServer(): BelongsTo
    {
        return $this->belongsTo(ProxmoxServer::class);
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    protected function casts(): array
    {
        return [
            'template_vmid' => 'integer',
            'is_enabled' => 'boolean',
            'verified_at' => 'datetime',
            'verification_snapshot' => 'array',
        ];
    }
}
