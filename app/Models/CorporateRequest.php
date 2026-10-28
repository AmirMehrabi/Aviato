<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CorporateRequest extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['company' => 'array', 'registry_result' => 'array', 'looked_up_at' => 'datetime', 'submitted_at' => 'datetime', 'reviewed_at' => 'datetime', 'consented_at' => 'datetime'];
    }

    public static function statuses(): array
    {
        return ['draft' => 'پیش‌نویس', 'pending' => 'در حال بررسی', 'needs_correction' => 'نیازمند اصلاح', 'approved' => 'تأیید شده', 'rejected' => 'رد شده'];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewer_id');
    }

    public function documents(): HasMany
    {
        return $this->hasMany(CorporateDocument::class);
    }

    public function events(): HasMany
    {
        return $this->hasMany(CorporateRequestEvent::class)->orderBy('id');
    }
}
