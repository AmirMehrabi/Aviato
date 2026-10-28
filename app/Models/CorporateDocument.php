<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateDocument extends Model
{
    protected $guarded = [];

    public function request()
    {
        return $this->belongsTo(CorporateRequest::class, 'corporate_request_id');
    }
}
