<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['storage_bucket_id', 'object_key', 'object_key_hash', 'size_bytes', 'etag', 'content_type', 'metadata', 'storage_path'])]
class StorageObject extends Model
{
    public function bucket(): BelongsTo { return $this->belongsTo(StorageBucket::class, 'storage_bucket_id'); }

    protected function casts(): array { return ['size_bytes' => 'integer', 'metadata' => 'array']; }
}
