<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'slug', 'description', 'support_team_id', 'assignment_strategy', 'is_active', 'sort_order'])]
class TicketCategory extends Model
{
    public const ASSIGNMENT_ROUND_ROBIN = 'round_robin';

    public const ASSIGNMENT_MANUAL = 'manual';

    public static function strategies(): array
    {
        return [
            self::ASSIGNMENT_ROUND_ROBIN => 'Auto assign - round robin',
            self::ASSIGNMENT_MANUAL => 'Manual assignment',
        ];
    }

    public function supportTeam(): BelongsTo
    {
        return $this->belongsTo(SupportTeam::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
