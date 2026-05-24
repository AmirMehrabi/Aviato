<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable([
    'wallet_id',
    'customer_id',
    'created_by_id',
    'type',
    'amount',
    'balance_before',
    'balance_after',
    'description',
    'reference_type',
    'reference_id',
    'metadata',
])]
class WalletTransaction extends Model
{
    public const TYPE_CREDIT = 'credit';

    public const TYPE_DEBIT = 'debit';

    public const TYPE_CHARGE = 'charge';

    public const TYPE_REFUND = 'refund';

    public const TYPE_ADJUSTMENT = 'adjustment';

    public function wallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class);
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function reference(): MorphTo
    {
        return $this->morphTo();
    }

    public function isUsageCharge(): bool
    {
        return $this->type === self::TYPE_CHARGE
            && ($this->metadata['category'] ?? null) === 'payg_usage';
    }

    protected function casts(): array
    {
        return [
            'amount' => 'integer',
            'balance_before' => 'integer',
            'balance_after' => 'integer',
            'metadata' => 'array',
        ];
    }
}
