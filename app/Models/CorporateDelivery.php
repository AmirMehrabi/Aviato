<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateDelivery extends Model
{
    protected $guarded = [];

    public function event()
    {
        return $this->belongsTo(CorporateRequestEvent::class, 'corporate_request_event_id');
    }
}
