<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['project_id', 'name', 'region', 'status', 'quota_bytes', 'usage_bytes', 'object_count'])]
class StorageBucket extends Model
{
    public const STATUS_ACTIVE = 'active';

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    public function objects(): HasMany { return $this->hasMany(StorageObject::class); }

    public function multipartUploads(): HasMany { return $this->hasMany(StorageMultipartUpload::class); }

    protected function casts(): array
    {
        return ['quota_bytes' => 'integer', 'usage_bytes' => 'integer', 'object_count' => 'integer'];
    }
}
