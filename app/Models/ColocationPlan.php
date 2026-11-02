<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ColocationPlan extends Model
{
    protected $guarded = ['id', 'uuid'];

    public const INTERVALS = ['monthly' => 'ماهانه', 'quarterly' => 'سه‌ماهه', 'yearly' => 'سالانه'];

    public const TRAFFIC_POLICIES = ['included' => 'سهمیه ماهانه + مازاد', 'metered' => 'بدون سهمیه، بر اساس مصرف', 'unmetered' => 'بدون هزینه حجمی', 'separate' => 'قرارداد ارتباطی جداگانه'];

    protected static function booted(): void
    {
        static::creating(fn (self $plan) => $plan->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['available' => 'boolean', 'monthly_price' => 'integer', 'quarterly_price' => 'integer', 'yearly_price' => 'integer', 'setup_fee' => 'integer', 'included_traffic_gb' => 'integer', 'traffic_price_per_gb' => 'integer', 'tax_rate' => 'float'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function intervals(): array
    {
        return array_filter(self::INTERVALS, fn ($key) => $this->{$key.'_price'} !== null, ARRAY_FILTER_USE_KEY);
    }
}
