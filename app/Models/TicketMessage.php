<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Str;

#[Fillable(['ticket_id', 'author_type', 'author_id', 'type', 'body', 'seen_by_customer_at', 'seen_by_admin_at'])]
class TicketMessage extends Model
{
    public const TYPE_REPLY = 'reply';

    public const TYPE_INTERNAL = 'internal';

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function author(): MorphTo
    {
        return $this->morphTo();
    }

    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    public function renderedBody(): string
    {
        return Str::markdown($this->body, [
            'html_input' => 'strip',
            'allow_unsafe_links' => false,
        ]);
    }

    protected function casts(): array
    {
        return [
            'seen_by_customer_at' => 'datetime',
            'seen_by_admin_at' => 'datetime',
        ];
    }
}
