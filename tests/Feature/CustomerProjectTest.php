<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\ProjectAccessService;
use App\Services\UsageBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerProjectTest extends TestCase
{
    use RefreshDatabase;

    private string $customerBaseUrl = 'https://cp.localhost';

    public function test_customer_factory_creates_default_project_owner_membership(): void
    {
        $customer = Customer::factory()->create();
        $project = $customer->ownedProjects()->where('is_default', true)->firstOrFail();

        $this->assertSame('Default Project', $project->name);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
    }

    public function test_customer_can_create_workspace_and_it_becomes_active(): void
    {
        $customer = Customer::factory()->create();

        $this->actingAs($customer, 'customer');

        $response = $this->post($this->customerBaseUrl.'/projects', [
            'name' => 'Production Servers',
        ]);

        $project = $customer->ownedProjects()->where('name', 'Production Servers')->firstOrFail();

        $response->assertRedirect($this->customerBaseUrl.'/projects/'.$project->uuid);
        $response->assertSessionHas(ProjectAccessService::SESSION_KEY, $project->id);
        $this->assertDatabaseHas('project_members', [
            'project_id' => $project->id,
            'customer_id' => $customer->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
    }

    public function test_owner_and_admin_member_can_rename_workspace(): void
    {
        $owner = Customer::factory()->create();
        $admin = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create([
            'customer_id' => $admin->id,
            'role' => ProjectMember::ROLE_ADMIN,
        ]);

        $this->actingAs($owner, 'customer');
        $this->patch($this->customerBaseUrl.'/projects/'.$project->uuid, [
            'name' => 'Owner Renamed',
        ])->assertSessionHas('status');

        $this->assertSame('Owner Renamed', $project->fresh()->name);

        $this->actingAs($admin, 'customer');
        $this->patch($this->customerBaseUrl.'/projects/'.$project->uuid, [
            'name' => 'Admin Renamed',
        ])->assertSessionHas('status');

        $this->assertSame('Admin Renamed', $project->fresh()->name);
    }

    public function test_regular_member_cannot_rename_workspace(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_MEMBER,
        ]);

        $this->actingAs($member, 'customer');

        $this->patch($this->customerBaseUrl.'/projects/'.$project->uuid, [
            'name' => 'Not Allowed',
        ])->assertNotFound();

        $this->assertNotSame('Not Allowed', $project->fresh()->name);
    }

    public function test_customer_can_only_switch_to_accessible_workspaces(): void
    {
        $customer = Customer::factory()->create();
        $accessible = $customer->ensureDefaultProject();
        $outsider = Customer::factory()->create()->ensureDefaultProject();

        $this->actingAs($customer, 'customer');

        $this->post($this->customerBaseUrl.'/projects/switch', [
            'project_id' => $accessible->id,
        ])->assertSessionHas(ProjectAccessService::SESSION_KEY, $accessible->id);

        $this->post($this->customerBaseUrl.'/projects/switch', [
            'project_id' => $outsider->id,
        ])->assertNotFound();
    }

    public function test_dashboard_explains_the_active_workspace_context(): void
    {
        $customer = Customer::factory()->create();
        $workspace = $customer->ownedProjects()->create([
            'name' => 'Production Servers',
            'slug' => 'production-servers',
            'is_default' => false,
        ]);
        $workspace->members()->create([
            'customer_id' => $customer->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
        $customer->wallet()->update(['balance' => 1000000]);

        $this->actingAs($customer, 'customer');
        $this->withSession([ProjectAccessService::SESSION_KEY => $workspace->id])
            ->get($this->customerBaseUrl.'/dashboard')
            ->assertOk()
            ->assertSee('فضای کاری فعال')
            ->assertSee('Production Servers')
            ->assertSee('این داشبورد، ماشین‌ها، هزینه‌ها و دسترسی‌های مربوط به همین فضا را نمایش می‌دهد.')
            ->assertSee('مدیریت فضاهای کاری');
    }

    public function test_project_member_can_see_project_vm_but_non_member_cannot_guess_it(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $outsider = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_MEMBER,
        ]);
        $owner->wallet()->update(['balance' => 1000000]);

        $vm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'created_by_customer_id' => $member->id,
            'name' => 'project-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($member, 'customer');
        $this->get($this->customerBaseUrl.'/servers/'.$vm->uuid)
            ->assertOk()
            ->assertDontSee('جزئیات فنی')
            ->assertDontSee('Network Bridge')
            ->assertDontSee('OS Template')
            ->assertDontSee('vmbr1');

        $outsider->wallet()->update(['balance' => 1000000]);
        $this->actingAs($outsider, 'customer');
        $this->get($this->customerBaseUrl.'/servers/'.$vm->uuid)->assertNotFound();
    }

    public function test_project_member_only_sees_own_vms_in_workspace_and_listings(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_MEMBER,
        ]);
        $owner->wallet()->update(['balance' => 1000000]);

        $ownerVm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'owner-private-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $memberVm = VirtualMachine::create([
            'customer_id' => $member->id,
            'project_id' => $project->id,
            'created_by_customer_id' => $member->id,
            'name' => 'member-visible-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($member, 'customer');

        $this->get($this->customerBaseUrl.'/servers')
            ->assertOk()
            ->assertSee('member-visible-vm')
            ->assertDontSee('owner-private-vm');

        $this->get($this->customerBaseUrl.'/projects/'.$project->uuid)->assertOk();

        $this->get($this->customerBaseUrl.'/servers/'.$memberVm->uuid)->assertOk();
        $this->get($this->customerBaseUrl.'/servers/'.$ownerVm->uuid)->assertNotFound();
    }

    public function test_customer_with_specific_vm_access_can_open_workspace(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'specific-access-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
        $membership = $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_MEMBER,
            'vm_access_scope' => ProjectMember::VM_ACCESS_SPECIFIC,
        ]);
        $membership->specificVirtualMachines()->attach($vm->id);

        $this->actingAs($member, 'customer');

        $this->post($this->customerBaseUrl.'/projects/switch', [
            'project_id' => $project->id,
        ])->assertSessionHas(ProjectAccessService::SESSION_KEY, $project->id);

        $this->get($this->customerBaseUrl.'/projects/'.$project->uuid)->assertOk();
    }

    public function test_customer_role_update_keeps_existing_vm_scope(): void
    {
        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'created_by_customer_id' => $member->id,
            'name' => 'member-specific-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
        $membership = $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_ADMIN,
            'vm_access_scope' => ProjectMember::VM_ACCESS_SPECIFIC,
        ]);
        $membership->specificVirtualMachines()->attach($vm->id);

        $this->actingAs($member, 'customer');

        $this->patch($this->customerBaseUrl.'/projects/'.$project->uuid.'/members/'.$membership->id, [
            'role' => ProjectMember::ROLE_VIEWER,
        ])->assertSessionHas('status');

        $membership = $membership->fresh();

        $this->assertSame(ProjectMember::VM_ACCESS_SPECIFIC, $membership->vm_access_scope);
        $this->assertSame(ProjectMember::ROLE_VIEWER, $membership->role);
        $this->assertDatabaseHas('project_member_virtual_machines', [
            'project_member_id' => $membership->id,
            'virtual_machine_id' => $vm->id,
        ]);
    }

    public function test_project_owner_is_charged_for_member_created_vm_usage(): void
    {
        CarbonImmutable::setTestNow('2026-06-15 12:00:00');

        $owner = Customer::factory()->create();
        $member = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_MEMBER,
        ]);
        $bundle = VmBundle::create([
            'name' => 'Starter',
            'slug' => 'starter',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'monthly_price' => 730000,
            'hourly_price' => 1000,
            'is_active' => true,
            'sort_order' => 1,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'created_by_customer_id' => $member->id,
            'vm_bundle_id' => $bundle->id,
            'name' => 'member-built-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
            'last_billed_at' => now()->subHours(3),
        ]);

        $accrual = app(UsageBillingService::class)->accrueVm($vm);

        $this->assertSame(3000, $accrual->amount);
        $this->assertSame($project->id, $accrual->project_id);
        $this->assertSame($member->id, $accrual->snapshot['created_by_customer_id']);
        $this->assertSame(0, $owner->wallet()->firstOrFail()->balance);
        $this->assertSame(0, $member->wallet()->firstOrFail()->balance);
        app(UsageBillingService::class)->settleDate(now());
        $transaction = $owner->walletTransactions()->firstOrFail();
        $this->assertSame(-3000, $owner->wallet()->firstOrFail()->balance);
        $this->assertSame($project->id, $transaction->metadata['project_id']);

        CarbonImmutable::setTestNow();
    }
}
