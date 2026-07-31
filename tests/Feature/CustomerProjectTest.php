<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\ProxmoxServer;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmTransfer;
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

        $response->assertRedirect($this->customerBaseUrl.'/dashboard');
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
        ])->assertRedirect($this->customerBaseUrl.'/dashboard')
            ->assertSessionHas(ProjectAccessService::SESSION_KEY, $accessible->id);

        $this->post($this->customerBaseUrl.'/projects/switch', [
            'project_id' => $outsider->id,
        ])->assertNotFound();
    }

    public function test_opening_workspace_management_does_not_change_active_workspace(): void
    {
        $customer = Customer::factory()->create();
        $default = $customer->ensureDefaultProject();
        $managed = $customer->ownedProjects()->create([
            'name' => 'Managed Workspace',
            'slug' => 'managed-workspace',
            'is_default' => false,
        ]);
        $managed->members()->create([
            'customer_id' => $customer->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);

        $this->actingAs($customer, 'customer');

        $this->withSession([ProjectAccessService::SESSION_KEY => $default->id])
            ->get($this->customerBaseUrl.'/projects/'.$managed->uuid)
            ->assertOk()
            ->assertSee('در حال مدیریت یک فضای کاری غیرفعال هستید')
            ->assertSessionHas(ProjectAccessService::SESSION_KEY, $default->id);
    }

    public function test_only_owner_can_set_default_without_switching_active_workspace(): void
    {
        $owner = Customer::factory()->create();
        $admin = Customer::factory()->create();
        $active = $owner->ensureDefaultProject();
        $candidate = $owner->ownedProjects()->create([
            'name' => 'New Default',
            'slug' => 'new-default',
            'is_default' => false,
        ]);
        $candidate->members()->create([
            'customer_id' => $owner->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
        $candidate->members()->create([
            'customer_id' => $admin->id,
            'role' => ProjectMember::ROLE_ADMIN,
        ]);

        $this->actingAs($admin, 'customer');
        $this->patch($this->customerBaseUrl.'/projects/'.$candidate->uuid.'/default')->assertNotFound();

        $this->actingAs($owner, 'customer');
        $this->withSession([ProjectAccessService::SESSION_KEY => $active->id])
            ->patch($this->customerBaseUrl.'/projects/'.$candidate->uuid.'/default')
            ->assertSessionHas(ProjectAccessService::SESSION_KEY, $active->id)
            ->assertSessionHas('status');

        $this->assertFalse($active->fresh()->is_default);
        $this->assertTrue($candidate->fresh()->is_default);
    }

    public function test_owner_can_delete_empty_non_default_workspace_and_active_session_falls_back(): void
    {
        $owner = Customer::factory()->create();
        $default = $owner->ensureDefaultProject();
        $project = $owner->ownedProjects()->create([
            'name' => 'Disposable Workspace',
            'slug' => 'disposable-workspace',
            'is_default' => false,
        ]);
        $project->members()->create([
            'customer_id' => $owner->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);

        $this->actingAs($owner, 'customer');
        $this->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->delete($this->customerBaseUrl.'/projects/'.$project->uuid, [
                'confirmation' => 'Disposable Workspace',
            ])
            ->assertRedirect($this->customerBaseUrl.'/projects')
            ->assertSessionHas(ProjectAccessService::SESSION_KEY, $default->id);

        $this->assertDatabaseMissing('projects', ['id' => $project->id]);
    }

    public function test_workspace_deletion_is_blocked_for_default_or_non_empty_workspace(): void
    {
        $owner = Customer::factory()->create();
        $default = $owner->ensureDefaultProject();
        $project = $owner->ownedProjects()->create([
            'name' => 'Busy Workspace',
            'slug' => 'busy-workspace',
            'is_default' => false,
        ]);
        $project->members()->create([
            'customer_id' => $owner->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
        VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'busy-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
        ]);

        $this->actingAs($owner, 'customer');
        $this->delete($this->customerBaseUrl.'/projects/'.$default->uuid, [
            'confirmation' => $default->name,
        ])->assertSessionHasErrors('delete');
        $this->delete($this->customerBaseUrl.'/projects/'.$project->uuid, [
            'confirmation' => $project->name,
        ])->assertSessionHasErrors('delete');

        $this->assertDatabaseHas('projects', ['id' => $default->id]);
        $this->assertDatabaseHas('projects', ['id' => $project->id]);
    }

    public function test_deleting_workspace_preserves_completed_transfer_history(): void
    {
        $owner = Customer::factory()->create();
        $default = $owner->ensureDefaultProject();
        $project = $owner->ownedProjects()->create([
            'name' => 'Transferred Workspace',
            'slug' => 'transferred-workspace',
            'is_default' => false,
        ]);
        $project->members()->create([
            'customer_id' => $owner->id,
            'role' => ProjectMember::ROLE_OWNER,
        ]);
        $vm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'transferred-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_DELETED,
            'deleted_at' => now(),
        ]);
        $transfer = VmTransfer::create([
            'virtual_machine_id' => $vm->id,
            'from_customer_id' => $owner->id,
            'to_customer_id' => $owner->id,
            'from_project_id' => $project->id,
            'to_project_id' => $default->id,
            'initiated_by_user_id' => User::factory()->create()->id,
            'completed_at' => now(),
        ]);

        $this->actingAs($owner, 'customer');
        $this->delete($this->customerBaseUrl.'/projects/'.$project->uuid, [
            'confirmation' => $project->name,
        ])->assertSessionHas('status');

        $this->assertDatabaseHas('vm_transfers', [
            'id' => $transfer->id,
            'from_project_id' => null,
            'to_project_id' => $default->id,
        ]);
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
            ->assertSee('هنوز ماشینی ندارید')
            ->assertSee('مدیریت فضاهای کاری');
    }

    public function test_dashboard_prioritizes_vm_status_and_console_actions(): void
    {
        $customer = Customer::factory()->create();
        $project = $customer->ensureDefaultProject();
        $customer->wallet()->update(['balance' => 1000000]);
        $server = ProxmoxServer::create([
            'name' => 'Dashboard Proxmox',
            'datacenter' => 'THR-1',
            'host' => 'pve.local',
            'port' => 8006,
            'realm' => 'pam',
            'username' => 'root',
            'api_token_id' => 'root@pam!panel',
            'api_token_secret' => 'secret',
            'verify_tls' => false,
            'is_active' => true,
            'maintenance_mode' => false,
        ]);
        $runningVm = VirtualMachine::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 101,
            'name' => 'dashboard-running-vm',
            'hostname' => 'dashboard-running-vm',
            'node' => 'pve1',
            'ip_address' => '192.168.10.10',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 40,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
        VirtualMachine::create([
            'customer_id' => $customer->id,
            'project_id' => $project->id,
            'proxmox_server_id' => $server->id,
            'vmid' => 102,
            'name' => 'dashboard-stopped-vm',
            'hostname' => 'dashboard-stopped-vm',
            'node' => 'pve1',
            'ip_address' => '192.168.10.11',
            'cpu_cores' => 4,
            'ram_gb' => 8,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_STOPPED,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($customer, 'customer')
            ->get($this->customerBaseUrl.'/dashboard')
            ->assertOk()
            ->assertSee('ماشین‌های من')
            ->assertSee('dashboard-running-vm')
            ->assertSee('dashboard-stopped-vm')
            ->assertSee('باز کردن کنسول')
            ->assertSee(route('customer.servers.console.show', $runningVm, false))
            ->assertSee('خاموش')
            ->assertSee('مدیریت ماشین')
            ->assertSee('شارژ کیف پول')
            ->assertDontSee('آخرین تراکنش ها');
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
