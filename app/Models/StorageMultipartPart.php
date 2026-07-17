<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['storage_multipart_upload_id', 'part_number', 'size_bytes', 'etag', 'storage_path'])]
class StorageMultipartPart extends Model
{
    public function upload(): BelongsTo { return $this->belongsTo(StorageMultipartUpload::class, 'storage_multipart_upload_id'); }

    protected function casts(): array { return ['part_number' => 'integer', 'size_bytes' => 'integer']; }
}
