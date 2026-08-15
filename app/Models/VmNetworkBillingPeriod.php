<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['virtual_machine_id', 'customer_id', 'project_id', 'period_start', 'period_end', 'timezone', 'direction', 'included_bytes', 'price_per_unit', 'price_unit_bytes', 'currency', 'policy_snapshot', 'ingress_bytes', 'egress_bytes', 'rated_bytes', 'billable_bytes', 'accrued_amount', 'status'])]
class VmNetworkBillingPeriod extends Model
{
    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime', 'period_end' => 'datetime', 'policy_snapshot' => 'array',
            'included_bytes' => 'integer', 'price_per_unit' => 'integer', 'price_unit_bytes' => 'integer',
            'ingress_bytes' => 'integer', 'egress_bytes' => 'integer', 'rated_bytes' => 'integer',
            'billable_bytes' => 'integer', 'accrued_amount' => 'integer',
        ];
    }
}
