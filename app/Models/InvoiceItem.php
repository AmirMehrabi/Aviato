<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'invoice_id',
    'virtual_machine_id',
    'type',
    'label',
    'description',
    'quantity',
    'unit',
    'unit_price',
    'subtotal',
    'meta',
])]
class InvoiceItem extends Model
{
    public const TYPE_VM_USAGE = 'vm_usage';

    public const TYPE_TAX = 'tax';

    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    protected function casts(): array
    {
        return [
            'quantity' => 'decimal:4',
            'unit_price' => 'decimal:6',
            'subtotal' => 'integer',
            'meta' => 'array',
        ];
    }
}
