<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['customer_id', 'label', 'details'])]
class DomainContact extends Model
{
    protected function casts(): array
    {
        return ['details' => 'encrypted:array'];
    }
}
