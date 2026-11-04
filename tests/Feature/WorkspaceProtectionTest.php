<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\AppSetting;
use App\Models\Customer;
use App\Models\Project;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Services\CustomerVmQuotaService;
use App\Services\WorkspaceProtectionSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class WorkspaceProtectionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['portals.admin.domain' => 'admin.localhost', 'portals.customer.domain' => 'cp.localhost']);
    }

    private function payload(array $custom = []): array
    {
        $fields = [];
        foreach (app(WorkspaceProtectionSettings::class)->defaults() as $key => $value) {
            $fields[$key] = ['mode' => array_key_exists($key, $custom) ? 'custom' : 'default', 'value' => $custom[$key] ?? $value];
        }

        return ['protection' => $fields];
    }

    public function test_admin_can_save_zero_overrides_and_reset_to_live_defaults(): void
    {
        $project = Customer::factory()->create()->ensureDefaultProject();
        $admin = User::factory()->create();
        $url = 'https://admin.localhost/workspaces/'.$project->uuid;
        $this->actingAs($admin, 'admin')->get($url)->assertOk()->assertSee('محافظت فضای کاری')->assertSee('protection[wallet_retention_days][mode]', false);
        $this->patch($url.'/protection', $this->payload(['wallet_retention_days' => 14, 'vm_rebuild_fee_multiplier_percentage' => 0]))->assertRedirect($url.'#protection');
        $settings = app(WorkspaceProtectionSettings::class);
        $project->refresh();
        $this->assertSame(14, $settings->value($project, 'wallet_retention_days'));
        $this->assertEquals(0, $settings->value($project, 'vm_rebuild_fee_multiplier_percentage'));
        AppSetting::setValue(AppSetting::CUSTOMER_WALLET_RETENTION_DAYS, 9, 'integer', 'billing');
        $this->assertSame(14, $settings->value($project, 'wallet_retention_days'));
        $this->patch($url.'/protection', $this->payload())->assertSessionHasNoErrors();
        $this->assertNull($project->refresh()->protection_overrides);
        $this->assertSame(9, $settings->value($project, 'wallet_retention_days'));
    }

    public function test_invalid_or_incomplete_overrides_do_not_change_settings(): void
    {
        $project = Customer::factory()->create()->ensureDefaultProject();
        $url = 'https://admin.localhost/workspaces/'.$project->uuid.'/protection';
        $this->actingAs(User::factory()->create(), 'admin')
            ->patch($url, $this->payload(['wallet_retention_days' => 0, 'wallet_shutdown_percentage' => 101]))
            ->assertSessionHasErrors(['protection.wallet_retention_days.value', 'protection.wallet_shutdown_percentage.value']);
        $this->patch($url, ['protection' => ['wallet_retention_days' => ['mode' => 'default']]])->assertSessionHasErrors();
        $this->assertNull($project->refresh()->protection_overrides);
    }

    public function test_only_admins_with_settings_and_workspace_management_can_override(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $url = 'https://admin.localhost/workspaces/'.$project->uuid.'/protection';
        $admin = User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['projects.read', 'projects.manage']]);
        $this->actingAs($admin, 'admin')->patch($url, $this->payload())->assertForbidden();
        $this->actingAs($admin, 'admin')->get('https://admin.localhost/workspaces/'.$project->uuid)->assertOk()->assertDontSee('protection[wallet_retention_days][mode]', false);
        $this->assertNull($project->refresh()->protection_overrides);
    }

    public function test_inherited_quota_stays_account_wide_and_custom_quota_counts_only_workspace(): void
    {
        $owner = Customer::factory()->create(['national_code_verified_at' => null]);
        $project = $owner->ensureDefaultProject();
        $other = Project::create(['name' => 'Other', 'owner_customer_id' => $owner->id]);
        foreach ([$other, $other] as $workspace) {
            Event::fakeFor(fn () => VirtualMachine::create([
                'customer_id' => $owner->id, 'project_id' => $workspace->id, 'name' => 'quota-vm',
                'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
                'status' => VirtualMachine::STATUS_RUNNING,
            ]));
        }
        AppSetting::setValue(AppSetting::CUSTOMER_UNVERIFIED_VM_LIMIT, 2, 'integer', 'customer');
        $quota = app(CustomerVmQuotaService::class);
        $this->assertFalse($quota->snapshot($owner, $project)['can_create']);
        $project->forceFill(['protection_overrides' => ['unverified_customer_vm_limit' => 1]])->save();
        $snapshot = $quota->snapshot($owner, $project);
        $this->assertSame(0, $snapshot['used']);
        $this->assertTrue($snapshot['can_create']);
        $this->assertFalse($quota->snapshot($owner, $other)['can_create']);
        $project->forceFill(['protection_overrides' => ['unverified_customer_vm_limit' => 0]])->save();
        $this->assertFalse($quota->snapshot($owner, $project)['can_create']);
    }

    public function test_api_options_and_customer_create_screen_use_workspace_quota(): void
    {
        $owner = Customer::factory()->create(['national_code_verified_at' => null]);
        $project = $owner->ensureDefaultProject();
        $project->forceFill(['protection_overrides' => ['unverified_customer_vm_limit' => 0]])->save();
        $token = $owner->createToken('Workspace reader', ['vm:read'])->plainTextToken;
        $this->withToken($token)->getJson('/api/v1/projects/'.$project->uuid.'/virtual-machines/options')
            ->assertOk()->assertJsonPath('data.quota.limit', 0)
            ->assertJsonPath('data.blocking_code', 'workspace_limit_reached');
        $this->actingAs($owner, 'customer')->get('https://cp.localhost/servers/create')
            ->assertOk()->assertSee('سقف سفارشی این فضای کاری تکمیل شده است.');
    }

    public function test_customer_cannot_update_protection_through_workspace_edit(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $this->actingAs($owner, 'customer')->patch('https://cp.localhost/projects/'.$project->uuid, [
            'name' => $project->name,
            'protection_overrides' => ['verified_customer_vm_limit' => 0],
        ])->assertRedirect();
        $this->assertNull($project->refresh()->protection_overrides);
    }

    public function test_verified_zero_limit_is_unlimited_and_cooldown_zero_releases_quota(): void
    {
        $owner = Customer::factory()->create(['national_code' => '0100000002', 'national_code_verified_at' => now()]);
        $project = $owner->ensureDefaultProject();
        $project->forceFill(['protection_overrides' => ['verified_customer_vm_limit' => 0]])->save();
        $quota = app(CustomerVmQuotaService::class);
        $this->assertTrue($quota->snapshot($owner, $project)['can_create']);
        $this->assertNull($quota->snapshot($owner, $project)['remaining']);
        $owner->forceFill(['national_code_verified_at' => null])->save();
        Event::fakeFor(fn () => VirtualMachine::create([
            'customer_id' => $owner->id, 'project_id' => $project->id, 'name' => 'deleted-quota-vm',
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10,
            'status' => VirtualMachine::STATUS_DELETED, 'deleted_at' => now()->subDay(),
        ]));
        $project->forceFill(['protection_overrides' => ['unverified_customer_vm_limit' => 1, 'deleted_vm_cooldown_days' => 30]])->save();
        $this->assertFalse($quota->snapshot($owner, $project)['can_create']);
        $project->forceFill(['protection_overrides' => ['unverified_customer_vm_limit' => 1, 'deleted_vm_cooldown_days' => 0]])->save();
        $this->assertTrue($quota->snapshot($owner, $project)['can_create']);
    }

    public function test_rebuild_fee_preserves_global_calculation_and_supports_free_override(): void
    {
        $project = Customer::factory()->create()->ensureDefaultProject();
        $settings = app(WorkspaceProtectionSettings::class);
        $this->assertSame(AppSetting::vmRebuildFeeAmount(1000000), $settings->rebuildFee($project, 1000000));
        $project->forceFill(['protection_overrides' => ['vm_rebuild_fee_multiplier_percentage' => 0]])->save();
        $this->assertSame(0, $settings->rebuildFee($project, 1000000));
    }
}
