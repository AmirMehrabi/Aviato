<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['promotion_campaign_id', 'promotion_code_id', 'wallet_id', 'customer_id', 'redeemed_by_customer_id', 'project_id', 'payment_id', 'wallet_transaction_id', 'base_amount', 'benefit_amount', 'redeemed_at'])]
class PromotionRedemption extends Model
{
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'promotion_campaign_id');
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(PromotionCode::class, 'promotion_code_id');
    }

    public function walletTransaction(): BelongsTo
    {
        return $this->belongsTo(WalletTransaction::class);
    }

    protected function casts(): array
    {
        return ['base_amount' => 'integer', 'benefit_amount' => 'integer', 'redeemed_at' => 'datetime'];
    }
}
