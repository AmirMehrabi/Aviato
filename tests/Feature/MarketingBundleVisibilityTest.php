<?php

namespace Tests\Feature;

use App\Models\VmBundle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingBundleVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_pages_hide_bundles_marked_as_not_for_marketing(): void
    {
        $visible = VmBundle::create([
            'name' => 'Visible',
            'slug' => 'visible',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => true,
            'show_on_marketing' => true,
        ]);

        $hidden = VmBundle::create([
            'name' => 'Hidden',
            'slug' => 'hidden',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 1490000,
            'is_active' => true,
            'show_on_marketing' => false,
        ]);

        $this->get('/')->assertOk()->assertViewHas('bundles', function ($bundles) use ($visible, $hidden): bool {
            return $bundles->contains('id', $visible->id) && ! $bundles->contains('id', $hidden->id);
        });

        $this->get('/pricing')->assertOk()->assertViewHas('bundles', function ($bundles) use ($visible, $hidden): bool {
            return $bundles->contains('id', $visible->id) && ! $bundles->contains('id', $hidden->id);
        });

        $this->get('/solutions')->assertOk()->assertViewHas('bundles', function ($bundles) use ($visible, $hidden): bool {
            return $bundles->contains('id', $visible->id) && ! $bundles->contains('id', $hidden->id);
        });
    }

    public function test_vm_bundle_defaults_to_visible_in_marketing(): void
    {
        $bundle = VmBundle::create([
            'name' => 'Visible by default',
            'slug' => 'visible-by-default',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => true,
        ]);

        $this->assertTrue((bool) $bundle->show_on_marketing);
    }

    public function test_admin_can_hide_a_bundle_from_marketing_pages(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin, 'admin');
        $this->post('https://admin.localhost/billing/bundles', [
            'name' => 'Private',
            'slug' => 'private',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => 1,
            'show_on_marketing' => 0,
        ])->assertRedirect('https://admin.localhost/billing/bundles');

        $this->assertDatabaseHas('vm_bundles', [
            'slug' => 'private',
            'show_on_marketing' => 0,
        ]);
    }
}
