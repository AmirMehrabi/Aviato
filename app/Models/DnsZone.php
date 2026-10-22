<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class DnsZone extends Model
{
    protected $guarded = ['id'];

    protected $attributes = ['revision' => 0, 'synced_revision' => 0, 'status' => 'unverified', 'delegation_status' => 'pending', 'plan' => 'free'];

    protected static function booted(): void
    {
        static::creating(fn (DnsZone $zone) => $zone->uuid ??= (string) Str::uuid());
    }

    public function getRouteKeyName(): string
    {
        return 'uuid';
    }

    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    public function recordSets(): HasMany
    {
        return $this->hasMany(DnsRecordSet::class);
    }

    public function changes(): HasMany
    {
        return $this->hasMany(DnsChange::class);
    }

    public function fqdn(): string
    {
        return $this->name.'.';
    }

    protected function casts(): array
    {
        return [
            'verification_expires_at' => 'datetime', 'verified_at' => 'datetime',
            'checked_at' => 'datetime', 'synced_at' => 'datetime', 'delete_requested_at' => 'datetime',
            'observed_nameservers' => 'array', 'node_status' => 'array',
            'revision' => 'integer', 'synced_revision' => 'integer',
        ];
    }
}
