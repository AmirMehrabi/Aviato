<?php

namespace App\Models;

use App\Services\Domains\DomainWorkspaceService;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['provider', 'uuid', 'customer_id', 'project_id', 'name', 'extension', 'status', 'provider_id', 'registrant', 'nameservers', 'registered_at', 'expires_at', 'synced_at', 'auto_renew', 'auto_renew_limit', 'auto_renew_consented_at'])]
class ManagedDomain extends Model
{
    protected $attributes = ['provider' => 'reseller_world'];

    protected static function booted(): void
    {
        static::creating(function (ManagedDomain $model): void {
            $model->project_id ??= app(DomainWorkspaceService::class)->firstOwned($model->customer)->id;
        });
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    protected function casts(): array
    {
        return ['registrant' => 'encrypted:array', 'nameservers' => 'array', 'registered_at' => 'datetime', 'expires_at' => 'datetime', 'synced_at' => 'datetime', 'auto_renew' => 'boolean', 'auto_renew_limit' => 'integer', 'auto_renew_consented_at' => 'datetime'];
    }

    public function needsAttention(): bool
    {
        return $this->status !== 'active' || ! $this->expires_at || $this->expires_at->lte(now()->addDays(30));
    }

    public function customerStatus(): string
    {
        return match (true) {
            $this->expires_at?->isPast() => 'منقضی شده',
            $this->status !== 'active' => 'نیازمند پیگیری',
            ! $this->expires_at => 'در انتظار تأیید تاریخ اعتبار',
            $this->expires_at->lte(now()->addDays(30)) => 'نزدیک به انقضا',
            default => 'فعال',
        };
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(DomainOrder::class);
    }
}
