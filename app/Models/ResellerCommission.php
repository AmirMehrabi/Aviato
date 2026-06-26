<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResellerCommission extends Model
{
    public const STATUS_PENDING = 'pending';

    public const STATUS_CREDITED = 'credited';

    public const STATUS_WITHDRAWN = 'withdrawn';

    protected $fillable = [
        'reseller_id',
        'customer_id',
        'usage_settlement_id',
        'service_date',
        'settlement_amount',
        'commission_pct',
        'commission_amount',
        'payout_method',
        'status',
        'wallet_transaction_id',
        'withdrawal_request_id',
        'credited_at',
    ];

    protected function casts(): array
    {
        return [
            'service_date' => 'date',
            'settlement_amount' => 'integer',
            'commission_amount' => 'integer',
            'credited_at' => 'datetime',
        ];
    }

    public function reseller(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'reseller_id');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'customer_id');
    }

    public function usageSettlement(): BelongsTo
    {
        return $this->belongsTo(UsageSettlement::class);
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    public function withdrawalRequest(): BelongsTo
    {
        return $this->belongsTo(ResellerWithdrawalRequest::class);
    }
}
