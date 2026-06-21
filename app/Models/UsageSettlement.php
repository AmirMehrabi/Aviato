<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'customer_id',
    'project_id',
    'scope_key',
    'service_date',
    'amount',
    'wallet_transaction_id',
    'settled_at',
])]
class UsageSettlement extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function accruals(): HasMany
    {
        return $this->hasMany(UsageAccrual::class);
    }

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'amount' => 'integer',
            'settled_at' => 'datetime',
        ];
    }
}
