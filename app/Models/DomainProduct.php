<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['extension', 'operation', 'period', 'contact_mode', 'enabled', 'supplier_price', 'retail_price', 'minimum_margin', 'markup_percent', 'automatic_pricing', 'synced_at'])]
class DomainProduct extends Model
{
    protected function casts(): array
    {
        return ['period' => 'integer', 'enabled' => 'boolean', 'supplier_price' => 'integer', 'retail_price' => 'integer', 'minimum_margin' => 'integer', 'markup_percent' => 'integer', 'automatic_pricing' => 'boolean', 'synced_at' => 'datetime'];
    }
}
