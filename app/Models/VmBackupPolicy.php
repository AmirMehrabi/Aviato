<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'virtual_machine_id',
    'is_enabled',
    'frequency',
    'preferred_time',
    'retention_count',
    'backup_storage',
    'mode',
    'compression',
    'last_run_at',
    'next_run_at',
])]
class VmBackupPolicy extends Model
{
    public const FREQUENCY_DAILY = 'daily';

    public const FREQUENCY_WEEKLY = 'weekly';

    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function backups(): HasMany
    {
        return $this->hasMany(VmBackup::class);
    }

    public function scheduleNext(?CarbonImmutable $from = null): void
    {
        $from ??= now()->toImmutable();
        [$hour, $minute] = array_pad(explode(':', (string) $this->preferred_time), 2, 0);
        $next = $from->setTime((int) $hour, (int) $minute);

        if ($next->lessThanOrEqualTo($from)) {
            $next = $this->frequency === self::FREQUENCY_WEEKLY
                ? $next->addWeek()
                : $next->addDay();
        }

        $this->forceFill(['next_run_at' => $next])->save();
    }

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'retention_count' => 'integer',
            'last_run_at' => 'datetime',
            'next_run_at' => 'datetime',
        ];
    }
}
