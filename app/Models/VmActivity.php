<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['virtual_machine_id', 'actor_customer_id', 'event', 'outcome', 'title', 'detail', 'metadata'])]
class VmActivity extends Model
{
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function actorCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'actor_customer_id');
    }
}
