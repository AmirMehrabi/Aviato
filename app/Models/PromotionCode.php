<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['promotion_campaign_id', 'code_digest', 'encrypted_code', 'status', 'reserved_payment_id', 'reserved_wallet_id', 'reserved_until', 'redeemed_at', 'revoked_at'])]
class PromotionCode extends Model
{
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'promotion_campaign_id');
    }

    public function reservedWallet(): BelongsTo
    {
        return $this->belongsTo(Wallet::class, 'reserved_wallet_id');
    }

    public function redemption(): HasOne
    {
        return $this->hasOne(PromotionRedemption::class);
    }

    protected function casts(): array
    {
        return ['encrypted_code' => 'encrypted', 'reserved_until' => 'datetime', 'redeemed_at' => 'datetime', 'revoked_at' => 'datetime'];
    }
}
