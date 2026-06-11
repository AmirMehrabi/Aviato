<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['customer_id', 'balance', 'is_locked', 'lock_reason', 'last_transaction_at', 'negative_notification_count', 'negative_notified_at'])]
class Wallet extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function transactions(): HasMany
    {
        return $this->hasMany(WalletTransaction::class)->latest();
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest();
    }
    
    

    protected function casts(): array
    {
        return [
            'balance' => 'integer',
            'is_locked' => 'boolean',
            'last_transaction_at' => 'datetime',
            'negative_notification_count' => 'integer',
            'negative_notified_at' => 'datetime',
        ];
    }
}
