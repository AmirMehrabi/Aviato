<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['request_id', 'customer_id', 'personal_access_token_id', 'token_fingerprint', 'method', 'route', 'status_code', 'failure_type', 'duration_ms', 'ip_address', 'user_agent', 'query', 'request_bytes', 'response_bytes'])]
class ApiRequestLog extends Model
{
    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    protected function casts(): array
    {
        return ['query' => 'array'];
    }
}
