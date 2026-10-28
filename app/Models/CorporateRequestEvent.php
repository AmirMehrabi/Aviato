<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateRequestEvent extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['snapshot' => 'array'];
    }

    public function actor()
    {
        return $this->belongsTo(User::class, 'actor_id');
    }

    public function request()
    {
        return $this->belongsTo(CorporateRequest::class, 'corporate_request_id');
    }
}
