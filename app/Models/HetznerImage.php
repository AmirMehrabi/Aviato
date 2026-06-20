<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'hetzner_account_id',
    'cloud_image_id',
    'remote_id',
    'name',
    'description',
    'type',
    'architecture',
    'os_flavor',
    'os_version',
    'deprecated',
    'is_active',
    'raw',
    'last_synced_at',
])]
class HetznerImage extends Model
{
    public function account(): BelongsTo
    {
        return $this->belongsTo(HetznerAccount::class, 'hetzner_account_id');
    }

    public function cloudImage(): BelongsTo
    {
        return $this->belongsTo(CloudImage::class);
    }

    protected function casts(): array
    {
        return [
            'remote_id' => 'integer',
            'deprecated' => 'boolean',
            'is_active' => 'boolean',
            'raw' => 'array',
            'last_synced_at' => 'datetime',
        ];
    }
}
