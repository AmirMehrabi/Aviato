<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['ticket_id', 'actor_type', 'actor_id', 'type', 'payload'])]
class TicketEvent extends Model
{
    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function actor(): MorphTo
    {
        return $this->morphTo();
    }

    protected function casts(): array
    {
        return [
            'payload' => 'array',
        ];
    }
}
