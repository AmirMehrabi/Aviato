<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['project_id', 'access_key_id', 'secret', 'secret_encrypted', 'description', 'status', 'last_used_at'])]
#[Hidden(['secret_encrypted'])]
class StorageAccessKey extends Model
{
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    public function project(): BelongsTo { return $this->belongsTo(Project::class); }

    protected function secret(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value, array $attributes): ?string => isset($attributes['secret_encrypted']) ? decrypt($attributes['secret_encrypted']) : null,
            set: fn (?string $value): array => ['secret_encrypted' => $value === null ? null : encrypt($value)],
        );
    }

    protected function casts(): array { return ['last_used_at' => 'datetime']; }
}
