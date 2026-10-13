<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class NetworkIntegrationRun extends Model
{
    protected $guarded = [];

    protected function casts(): array
    {
        return ['payload' => 'array', 'stats' => 'array', 'health' => 'array', 'errors' => 'array', 'started_at' => 'datetime', 'finished_at' => 'datetime'];
    }
}
