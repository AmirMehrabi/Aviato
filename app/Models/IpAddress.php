<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'ip_pool_id',
    'virtual_machine_id',
    'address',
    'status',
    'reserved_at',
    'assigned_at',
    'released_at',
])]
class IpAddress extends Model
{
    public const STATUS_AVAILABLE = 'available';

    public const STATUS_RESERVED = 'reserved';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_RELEASED = 'released';

    public function pool(): BelongsTo
    {
        return $this->belongsTo(IpPool::class, 'ip_pool_id');
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    protected function casts(): array
    {
        return [
            'reserved_at' => 'datetime',
            'assigned_at' => 'datetime',
            'released_at' => 'datetime',
        ];
    }
}
