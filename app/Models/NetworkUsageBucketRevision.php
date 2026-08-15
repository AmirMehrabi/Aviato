<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['network_usage_bucket_id', 'revision', 'payload_hash', 'payload'])]
class NetworkUsageBucketRevision extends Model
{
    public function bucket(): BelongsTo
    {
        return $this->belongsTo(NetworkUsageBucket::class, 'network_usage_bucket_id');
    }

    protected function casts(): array
    {
        return ['revision' => 'integer', 'payload' => 'array'];
    }
}
