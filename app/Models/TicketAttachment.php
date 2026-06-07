<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['ticket_message_id', 'disk', 'path', 'original_name', 'mime_type', 'size'])]
class TicketAttachment extends Model
{
    public function getRouteKeyName(): string
    {
        return 'id';
    }

    public function message(): BelongsTo
    {
        return $this->belongsTo(TicketMessage::class, 'ticket_message_id');
    }

    public function readableSize(): string
    {
        if ($this->size >= 1048576) {
            return number_format($this->size / 1048576, 1).' MB';
        }

        if ($this->size >= 1024) {
            return number_format($this->size / 1024, 1).' KB';
        }

        return $this->size.' B';
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
        ];
    }
}
