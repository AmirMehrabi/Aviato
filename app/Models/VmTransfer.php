<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class VmTransfer extends Model
{
    protected $fillable = [
        'virtual_machine_id',
        'from_customer_id',
        'to_customer_id',
        'from_project_id',
        'to_project_id',
        'initiated_by_user_id',
        'unbilled_amount_transferred',
        'notes',
        'snapshot_before',
        'snapshot_after',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'unbilled_amount_transferred' => 'integer',
            'snapshot_before' => 'array',
            'snapshot_after' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function fromCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'from_customer_id');
    }

    public function toCustomer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'to_customer_id');
    }

    public function fromProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'from_project_id');
    }

    public function toProject(): BelongsTo
    {
        return $this->belongsTo(Project::class, 'to_project_id');
    }

    public function initiatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'initiated_by_user_id');
    }
}
