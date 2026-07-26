<?php

namespace App\Models;

use Database\Factories\IncidentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'title', 'slug', 'status', 'affected_service', 'impact_summary', 'summary', 'root_cause',
    'customer_impact', 'resolution', 'next_steps', 'final_status', 'started_at', 'ended_at',
    'is_published', 'published_at', 'meta_description',
])]
class Incident extends Model
{
    use HasFactory;

    protected static function newFactory(): IncidentFactory
    {
        return IncidentFactory::new();
    }

    public const STATUS_INVESTIGATING = 'investigating';

    public const STATUS_IDENTIFIED = 'identified';

    public const STATUS_MONITORING = 'monitoring';

    public const STATUS_RESOLVED = 'resolved';

    public const STATUSES = [
        self::STATUS_INVESTIGATING,
        self::STATUS_IDENTIFIED,
        self::STATUS_MONITORING,
        self::STATUS_RESOLVED,
    ];

    public function timelineEvents(): HasMany
    {
        return $this->hasMany(IncidentTimelineEvent::class)->orderBy('occurred_at')->orderBy('sort_order');
    }

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'published_at' => 'datetime',
            'is_published' => 'boolean',
        ];
    }

    protected function durationMinutes(): Attribute
    {
        return Attribute::get(function (): ?int {
            if (! $this->started_at) {
                return null;
            }

            return $this->started_at->diffInMinutes($this->ended_at ?: now());
        });
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_INVESTIGATING => 'در حال بررسی',
            self::STATUS_IDENTIFIED => 'شناسایی شده',
            self::STATUS_MONITORING => 'پایش',
            self::STATUS_RESOLVED => 'رفع شده',
            default => ucfirst($this->status),
        };
    }

    public function statusCssClass(): string
    {
        return match ($this->status) {
            self::STATUS_INVESTIGATING => 'bg-amber-50 text-amber-700 ring-amber-200',
            self::STATUS_IDENTIFIED => 'bg-orange-50 text-orange-700 ring-orange-200',
            self::STATUS_MONITORING => 'bg-sky-50 text-sky-700 ring-sky-200',
            self::STATUS_RESOLVED => 'bg-emerald-50 text-emerald-700 ring-emerald-200',
            default => 'bg-slate-100 text-slate-600 ring-slate-200',
        };
    }
}
