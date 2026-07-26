<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['incident_id', 'occurred_at', 'title', 'description', 'sort_order'])]
class IncidentTimelineEvent extends Model
{
    public function incident(): BelongsTo
    {
        return $this->belongsTo(Incident::class);
    }

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }
}
