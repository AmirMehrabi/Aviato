<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['source', 'assignment_id', 'virtual_machine_id', 'vm_uuid', 'first_observed_at', 'last_observed_at'])]
class NetworkMeterAssignment extends Model
{
    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    protected function casts(): array
    {
        return ['first_observed_at' => 'datetime', 'last_observed_at' => 'datetime'];
    }
}
