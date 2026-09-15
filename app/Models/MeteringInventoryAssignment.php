<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MeteringInventoryAssignment extends Model
{
    protected $fillable = [
        'assignment_id', 'virtual_machine_id', 'vm_uuid', 'ip_address', 'mac_address',
        'provider', 'provider_server_id', 'provider_vm_id', 'node', 'display_name',
        'valid_from', 'valid_until', 'active', 'payload_hash',
    ];

    protected function casts(): array
    {
        return ['valid_from' => 'datetime', 'valid_until' => 'datetime', 'active' => 'boolean'];
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }
}
