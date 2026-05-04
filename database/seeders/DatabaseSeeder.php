<?php

namespace Database\Seeders;

use App\Models\AppSetting;
use App\Models\ResourceRate;
use App\Models\User;
use App\Models\VmBundle;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        AppSetting::setValue(AppSetting::BILLING_CURRENCY, 'IRR', 'string', 'billing');

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        foreach (ResourceRate::defaults() as $resource => $data) {
            ResourceRate::updateOrCreate(
                ['resource' => $resource],
                $data + [
                    'hourly_price' => $data['monthly_price'] / ResourceRate::hoursPerMonth(),
                    'is_active' => true,
                ],
            );
        }

        VmBundle::updateOrCreate(
            ['slug' => 'starter-2c-4g-50g'],
            [
                'name' => 'Starter 2C / 4GB / 50GB',
                'description' => 'A balanced starter VM bundle for common web workloads.',
                'cpu_cores' => 2,
                'ram_gb' => 4,
                'disk_gb' => 50,
                'ip_count' => 1,
                'monthly_price' => 790000,
                'is_active' => true,
                'sort_order' => 10,
            ],
        );
    }
}
