<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['promotion_campaign_id', 'promotion_code_id', 'payment_id', 'wallet_id', 'expected_bonus', 'status', 'resolution_note', 'resolved_by_id', 'resolved_at'])]
class PromotionException extends Model
{
    public function campaign(): BelongsTo
    {
        return $this->belongsTo(PromotionCampaign::class, 'promotion_campaign_id');
    }

    public function code(): BelongsTo
    {
        return $this->belongsTo(PromotionCode::class, 'promotion_code_id');
    }

    public function payment(): BelongsTo
    {
        return $this->belongsTo(Payment::class);
    }

    protected function casts(): array
    {
        return ['expected_bonus' => 'integer', 'resolved_at' => 'datetime'];
    }
}
