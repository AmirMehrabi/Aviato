<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['uuid', 'owner_customer_id', 'name', 'slug', 'is_default'])]
class Project extends Model
{
    protected static function booted(): void
    {
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

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
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
        ];
    }
}
