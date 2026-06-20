<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

#[Fillable(['name', 'slug', 'description', 'cpu_cores', 'ram_gb', 'disk_gb', 'ip_count', 'monthly_price', 'hourly_price', 'is_active', 'show_on_marketing', 'sort_order'])]
class VmBundle extends Model
{
    protected static function booted(): void
    {
        static::saving(function (VmBundle $bundle): void {
            if (blank($bundle->slug)) {
                $bundle->slug = Str::slug($bundle->name);
            }

            if (is_null($bundle->show_on_marketing)) {
                $bundle->show_on_marketing = true;
            }

            $bundle->hourly_price = $bundle->monthly_price / ResourceRate::hoursPerMonth();
        });
    }

    public function virtualMachines(): HasMany
    {
        return $this->hasMany(VirtualMachine::class);
    }

    public function cloudImages(): BelongsToMany
    {
        return $this->belongsToMany(CloudImage::class)
            ->orderBy('sort_order')
            ->orderBy('name');
    }

    public function locationMappings(): HasMany
    {
        return $this->hasMany(VmBundleLocationMapping::class);
    }

    protected function casts(): array
    {
        return [
            'cpu_cores' => 'integer',
            'ram_gb' => 'integer',
            'disk_gb' => 'integer',
            'ip_count' => 'integer',
            'monthly_price' => 'integer',
            'hourly_price' => 'decimal:6',
            'is_active' => 'boolean',
            'show_on_marketing' => 'boolean',
            'sort_order' => 'integer',
        ];
    }
}
