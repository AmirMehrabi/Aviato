<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['source', 'bucket_id', 'revision', 'status', 'virtual_machine_id', 'vm_uuid', 'assignment_id', 'interval_start', 'interval_end', 'ingress_bytes', 'egress_bytes', 'completeness', 'calculation_version', 'finalized_at', 'source_updated_at', 'payload_hash', 'payload', 'processing_status', 'processing_error', 'rated_at'])]
class NetworkUsageBucket extends Model
{
    public function virtualMachine(): BelongsTo
    {
        return $this->belongsTo(VirtualMachine::class);
    }

    public function revisions(): HasMany
    {
        return $this->hasMany(NetworkUsageBucketRevision::class);
    }

    public function ratings(): HasMany
    {
        return $this->hasMany(NetworkUsageRating::class);
    }

    protected function casts(): array
    {
        return [
            'revision' => 'integer', 'interval_start' => 'datetime', 'interval_end' => 'datetime',
            'ingress_bytes' => 'integer', 'egress_bytes' => 'integer', 'finalized_at' => 'datetime',
            'source_updated_at' => 'datetime', 'payload' => 'array', 'rated_at' => 'datetime',
        ];
    }
}
