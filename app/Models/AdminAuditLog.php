<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'actor_user_id', 'actor_name', 'actor_email', 'event', 'method', 'route_name',
    'path', 'target_type', 'target_id', 'result', 'status_code', 'ip_address',
    'user_agent', 'request_id', 'metadata', 'changes',
])]
class AdminAuditLog extends Model
{
    public $timestamps = false;

    protected function casts(): array
    {
        return ['metadata' => 'array', 'changes' => 'array', 'created_at' => 'datetime'];
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
