<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class DnsChange extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['before' => 'array', 'after' => 'array'];
    }
}
