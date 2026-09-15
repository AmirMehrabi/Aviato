<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MeteringInventoryChange extends Model
{
    protected $primaryKey = 'stream_id';

    protected $fillable = ['assignment_id', 'action', 'payload'];

    protected function casts(): array
    {
        return ['payload' => 'array'];
    }
}
