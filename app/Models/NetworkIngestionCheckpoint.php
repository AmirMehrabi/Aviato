<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['source', 'cursor', 'last_succeeded_at', 'last_attempted_at', 'last_error'])]
class NetworkIngestionCheckpoint extends Model
{
    protected function casts(): array
    {
        return ['last_succeeded_at' => 'datetime', 'last_attempted_at' => 'datetime'];
    }
}
