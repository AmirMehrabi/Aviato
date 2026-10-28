<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CorporateProfile extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['company' => 'array', 'approved_at' => 'datetime', 'billing_effective_at' => 'datetime'];
    }
}
