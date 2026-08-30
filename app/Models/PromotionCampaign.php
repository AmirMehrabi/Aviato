<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['public_id', 'name', 'type', 'claim_mode', 'audience', 'status', 'currency', 'credit_amount', 'percentage', 'minimum_top_up', 'maximum_bonus', 'code_count', 'maximum_liability', 'headline', 'message', 'terms', 'starts_at', 'expires_at', 'rules_locked_at', 'created_by_id', 'updated_by_id'])]
class PromotionCampaign extends Model
{
    public const TYPE_CREDIT = 'wallet_credit';

    public const TYPE_PERCENTAGE = 'top_up_percentage';

    public const CLAIM_PAYMENT_REQUIRED = 'payment_required';

    public const CLAIM_INSTANT = 'instant';

    public const AUDIENCE_ALL = 'all';

    public const AUDIENCE_NEW = 'new_customer';

    public const AUDIENCE_ALLOWLIST = 'allowlist';

    protected static function booted(): void
    {
        static::creating(fn (self $campaign) => $campaign->public_id ??= (string) Str::uuid());
    }

    public function codes(): HasMany
    {
        return $this->hasMany(PromotionCode::class);
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(PromotionRedemption::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(PromotionEvent::class);
    }

    public function allowedCustomers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'promotion_campaign_customer')->withTimestamps();
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_id');
    }

    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    public function isAvailable(): bool
    {
        return $this->status === 'active'
            && ($this->starts_at === null || $this->starts_at->lte(now()))
            && $this->expires_at->isFuture();
    }

    public function requiresPayment(): bool
    {
        return $this->type === self::TYPE_PERCENTAGE
            || $this->claim_mode === self::CLAIM_PAYMENT_REQUIRED;
    }

    protected function casts(): array
    {
        return ['credit_amount' => 'integer', 'percentage' => 'integer', 'minimum_top_up' => 'integer', 'maximum_bonus' => 'integer', 'code_count' => 'integer', 'maximum_liability' => 'integer', 'starts_at' => 'datetime', 'expires_at' => 'datetime', 'rules_locked_at' => 'datetime'];
    }
}
