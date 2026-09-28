<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProjectAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WorkspaceWalletRiskTest extends TestCase
{
    use RefreshDatabase;

    public function test_workspace_without_monthly_usage_has_no_low_balance_indicator(): void
    {
        $owner = Customer::factory()->create();

        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertDontSee('اعتبار رو به پایان')
            ->assertDontSee('اعتبار بحرانی')
            ->assertDontSee('موجودی تمام شده');
    }

    public function test_sidebar_shows_low_balance_in_usage_and_workspace_selector_for_financial_roles(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $admin = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project->members()->create(['customer_id' => $admin->id, 'role' => ProjectMember::ROLE_ADMIN]);
        $project->members()->create(['customer_id' => $member->id, 'role' => ProjectMember::ROLE_MEMBER]);
        $owner->wallet()->update(['balance' => 100000]);

        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('اعتبار رو به پایان')
            ->assertSee('اعتبار قابل استفاده: 14٪ هزینه ماهانه');
        $this->get('https://cp.localhost/projects')->assertOk()->assertSee('اعتبار رو به پایان');

        $this->actingAs($admin, 'customer')
            ->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('اعتبار رو به پایان')
            ->assertSee('اعتبار قابل استفاده: 14٪ هزینه ماهانه');

        $this->actingAs($member, 'customer')
            ->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertDontSee('اعتبار رو به پایان')
            ->assertDontSee('اعتبار قابل استفاده:');
    }

    public function test_sidebar_risk_tracks_workspace_threshold_and_depleted_balance(): void
    {
        [$owner, $project] = $this->billableWorkspace();
        $owner->wallet()->update(['balance' => 100000]);
        $project->update(['wallet_alert_thresholds' => [10, 5]]);

        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertDontSee('اعتبار رو به پایان');

        $owner->wallet()->update(['balance' => 35000]);
        $this->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('اعتبار بحرانی');

        $owner->wallet()->update(['balance' => 0]);
        $this->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('موجودی تمام شده');
    }

    public function test_workspace_selector_flags_another_workspace_without_labeling_active_wallet_as_low(): void
    {
        [$owner, $activeProject] = $this->billableWorkspace();
        $activeProject->update(['wallet_alert_thresholds' => [5]]);
        $otherProject = $owner->ownedProjects()->create(['name' => 'Other workspace']);
        $otherProject->members()->create(['customer_id' => $owner->id, 'role' => ProjectMember::ROLE_OWNER]);
        $owner->wallet()->update(['balance' => 100000]);

        $this->actingAs($owner, 'customer')
            ->get('https://cp.localhost/dashboard')
            ->assertOk()
            ->assertSee('1 فضای کاری نیازمند توجه')
            ->assertSee('Other workspace')
            ->assertSee('اعتبار رو به پایان')
            ->assertDontSee('اعتبار قابل استفاده:');
    }

    private function billableWorkspace(): array
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $bundle = VmBundle::create([
            'name' => 'Sidebar risk',
            'slug' => 'sidebar-risk',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 10,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'is_active' => true,
        ]);
        VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'risk-vm',
            'cpu_cores' => 1,
            'ram_gb' => 1,
            'disk_gb' => 10,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now(),
        ]);

        return [$owner, $project];
    }
}
