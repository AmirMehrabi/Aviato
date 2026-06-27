<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'number',
    'status',
    'period_start',
    'period_end',
    'issued_at',
    'currency',
    'subtotal_amount',
    'wallet_charged_amount',
    'adjustment_amount',
    'total_amount',
    'tax_amount',
    'tax_rate_percentage',
    'meta',
])]
class Invoice extends Model
{
    public const STATUS_DRAFT = 'draft';

    public const STATUS_ISSUED = 'issued';

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function items(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    protected function casts(): array
    {
        return [
            'period_start' => 'datetime',
            'period_end' => 'datetime',
            'issued_at' => 'datetime',
            'subtotal_amount' => 'integer',
            'wallet_charged_amount' => 'integer',
            'adjustment_amount' => 'integer',
            'total_amount' => 'integer',
            'tax_amount' => 'integer',
            'tax_rate_percentage' => 'float',
            'meta' => 'array',
        ];
    }
}
