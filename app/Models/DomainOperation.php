<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['domain_order_id', 'managed_domain_id', 'kind', 'status', 'track_id', 'payload', 'result_code', 'checked_at'])]
class DomainOperation extends Model
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'checked_at' => 'datetime'];
    }

    public function order(): BelongsTo
    {
        return $this->belongsTo(DomainOrder::class, 'domain_order_id');
    }

    public function domain(): BelongsTo
    {
        return $this->belongsTo(ManagedDomain::class, 'managed_domain_id');
    }
}
