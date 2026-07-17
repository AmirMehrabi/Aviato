<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['storage_bucket_id', 'upload_id', 'object_key', 'object_key_hash', 'content_type', 'metadata', 'status', 'expires_at'])]
class StorageMultipartUpload extends Model
{
    public function bucket(): BelongsTo { return $this->belongsTo(StorageBucket::class, 'storage_bucket_id'); }

    public function parts(): HasMany { return $this->hasMany(StorageMultipartPart::class); }

    protected function casts(): array { return ['metadata' => 'array', 'expires_at' => 'datetime']; }
}
