<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\VmBundle;
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
            'network_included_bytes_monthly' => 1099511627776,
            'network_overage_price' => 9000,
            'network_overage_price_unit_bytes' => 1073741824,
            'network_usage_direction' => 'both',
            'network_billing_timezone' => 'Asia/Tehran',
        ])->assertRedirect('https://admin.localhost/billing/bundles');

        $this->assertDatabaseHas('vm_bundles', [
            'slug' => 'private',
            'show_on_marketing' => 0,
        ]);
    }

    public function test_admin_can_uncheck_marketing_visibility_when_editing_a_bundle(): void
    {
        $admin = User::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Public bundle',
            'slug' => 'public-bundle',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'monthly_price' => 790000,
            'is_active' => true,
            'show_on_marketing' => true,
        ]);

        $this->actingAs($admin, 'admin');
        $this->put("https://admin.localhost/billing/bundles/{$bundle->id}", [
            'name' => $bundle->name,
            'slug' => $bundle->slug,
            'cpu_cores' => $bundle->cpu_cores,
            'ram_gb' => $bundle->ram_gb,
            'disk_gb' => $bundle->disk_gb,
            'ip_count' => $bundle->ip_count,
            'monthly_price' => $bundle->monthly_price,
            'is_active' => 1,
            'network_included_bytes_monthly' => 1099511627776,
            'network_overage_price' => 9000,
            'network_overage_price_unit_bytes' => 1073741824,
            'network_usage_direction' => 'both',
            'network_billing_timezone' => 'Asia/Tehran',
        ])->assertRedirect('https://admin.localhost/billing/bundles');

        $this->assertFalse($bundle->fresh()->show_on_marketing);

        $this->get("https://admin.localhost/billing/bundles/{$bundle->id}/edit")
            ->assertOk()
            ->assertDontSee('name="show_on_marketing" value="1" checked', false);
    }
}
