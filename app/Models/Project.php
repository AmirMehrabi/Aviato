<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

#[Fillable(['uuid', 'owner_customer_id', 'name', 'slug', 'is_default', 'wallet_alert_thresholds', 'wallet_alert_recipient_ids'])]
class Project extends Model
{
    protected static function booted(): void
    {
        static::deleting(function (Project $project): void {
            if ($project->dnsZones()->where('status', '!=', 'deleted')->exists()) {
                throw ValidationException::withMessages(['dns' => 'ابتدا دامنه‌های Cloud DNS را حذف کنید.']);
            }
        });

        static::creating(function (Project $project): void {
            $project->uuid ??= (string) Str::uuid();
            $project->slug ??= Str::slug($project->name ?: 'project') ?: 'project';
        });
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(Customer::class, 'owner_customer_id');
    }

    public function members(): HasMany
    {
        return $this->hasMany(ProjectMember::class);
    }

    public function domains(): HasMany
    {
        return $this->hasMany(ManagedDomain::class);
    }

    public function domainOrders(): HasMany
    {
        return $this->hasMany(DomainOrder::class);
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function dnsZones(): HasMany
    {
        return $this->hasMany(DnsZone::class);
    }

    public function storageBuckets(): HasMany
    {
        return $this->hasMany(StorageBucket::class);
    }

    public function storageAccessKeys(): HasMany
    {
        return $this->hasMany(StorageAccessKey::class);
    }

    public function scopeVisibleTo(Builder $query, Customer $customer): Builder
    {
        return $query->whereHas('members', fn (Builder $query) => $query->where('customer_id', $customer->id));
    }

    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'wallet_alert_thresholds' => 'array',
            'wallet_alert_recipient_ids' => 'array',
        ];
    }
}
