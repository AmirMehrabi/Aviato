<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class ColocationService extends Model
{
    protected $guarded = ['id', 'uuid'];

    public const STATUSES = ['awaiting_payment' => 'در انتظار پرداخت', 'awaiting_installation' => 'پرداخت‌شده؛ در انتظار نصب', 'received' => 'تجهیزات دریافت شد', 'installing' => 'در حال نصب و اتصال', 'testing' => 'در حال آزمایش', 'active' => 'فعال', 'cancelled' => 'پایان‌یافته'];

    protected static function booted(): void
    {
        static::creating(fn (self $service) => $service->uuid = (string) Str::uuid());
    }

    protected function casts(): array
    {
        return ['agreement' => 'array', 'accepted_at' => 'immutable_datetime', 'paid_at' => 'immutable_datetime', 'installed_at' => 'immutable_datetime', 'billing_starts_at' => 'immutable_datetime', 'paid_through' => 'immutable_datetime', 'cancellation_requested_at' => 'immutable_datetime', 'ends_at' => 'immutable_datetime'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(ColocationEvent::class);
    }

    public function boundary(int $period): CarbonImmutable
    {
        $months = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12][$this->billing_interval];

        return $this->billing_starts_at->setTimezone('Asia/Tehran')->addMonthsNoOverflow($months * $period)->setTimezone(config('app.timezone'));
    }

    public function label(): string
    {
        if ($this->status === 'active' && $this->billing_starts_at?->isFuture()) {
            return 'نصب تکمیل شد؛ در انتظار تاریخ شروع';
        }

        return self::STATUSES[$this->status] ?? $this->status;
    }
}
