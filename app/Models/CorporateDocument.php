<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateDocument extends Model
{
    protected $guarded = [];

    public static function labels(): array
    {
        return [
            'incorporation' => 'آگهی تأسیس',
            'signatories' => 'آخرین آگهی تغییرات صاحبان امضا',
            'authorization' => 'نامه رسمی با سربرگ شرکت و امضای صاحبان امضا',
        ];
    }

    public function request()
    {
        return $this->belongsTo(CorporateRequest::class, 'corporate_request_id');
    }
}
