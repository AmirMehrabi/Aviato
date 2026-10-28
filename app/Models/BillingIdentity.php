<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BillingIdentity extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['identity' => 'encrypted:array', 'effective_at' => 'datetime'];
    }
}
