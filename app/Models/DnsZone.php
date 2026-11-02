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

    public function connectionState(): array
    {
        $published = $this->verified_at && $this->status === 'ready' && $this->synced_revision === $this->revision && ! $this->last_error;
        $connected = $this->delegation_status === 'active';
        $label = match (true) {
            in_array($this->status, ['suspended', 'deleting', 'deleted'], true) => 'سرویس این دامنه فعال نیست',
            (bool) $this->last_error => 'اتصال نیازمند بررسی',
            ! $this->verified_at => 'نیازمند تأیید مالکیت',
            ! $published => 'تغییرات در انتظار انتشار هستند',
            $connected => 'دامنه به آویاتو متصل است',
            default => 'رکوردها منتشر شده‌اند؛ اتصال نیم‌سرورها در انتظار بررسی است',
        };

        return ['label' => $label, 'published' => (bool) $published, 'connected' => $connected,
            'healthy' => (bool) ($published && $connected), 'observed' => $this->observed_nameservers ?? [],
            'checked' => $this->checked_at?->diffForHumans() ?? 'هنوز بررسی نشده',
            'synced' => $this->synced_at?->diffForHumans() ?? 'هنوز منتشر نشده', 'revision' => $this->revision];
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
