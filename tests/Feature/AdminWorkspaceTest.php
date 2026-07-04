<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class AdminWorkspaceTest extends TestCase
{
    use RefreshDatabase;

    private string $adminBaseUrl = 'https://admin.localhost';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_admin_can_view_workspaces_index(): void
    {
        $admin = User::factory()->create();
        $customer = Customer::factory()->create(['name' => 'John Owner']);
        $project = $customer->ensureDefaultProject();
        $project->update(['name' => 'Production Servers']);

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/workspaces')
            ->assertOk()
            ->assertSee('فضاهای کاری')
            ->assertSee('Production Servers')
            ->assertSee('John Owner');
    }

    public function test_admin_can_rename_any_workspace(): void
    {
        $admin = User::factory()->create();
        $project = Customer::factory()->create()->ensureDefaultProject();

        $this->actingAs($admin, 'admin');

        $this->patch($this->adminBaseUrl.'/workspaces/'.$project->uuid, [
            'name' => 'Admin Renamed Workspace',
        ])->assertSessionHas('status');

        $this->assertSame('Admin Renamed Workspace', $project->fresh()->name);
    }

    public function test_admin_workspace_show_contains_members_and_machine_context(): void
    {
        $admin = User::factory()->create();
        $owner = Customer::factory()->create(['name' => 'John Owner']);
        $member = Customer::factory()->create(['name' => 'Sarah Member']);
        $project = $owner->ensureDefaultProject();
        $project->members()->create([
            'customer_id' => $member->id,
            'role' => ProjectMember::ROLE_MEMBER,
        ]);
        VirtualMachine::create([
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

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/workspaces/'.$project->uuid)
            ->assertOk()
            ->assertSee('John Owner')
            ->assertSee('Sarah Member')
            ->assertSee('project-vm')
            ->assertSee('مسئول پرداخت')
            ->assertSee('اعضا و دسترسی‌ها');
    }

    public function test_admin_can_add_update_and_remove_workspace_member_access(): void
    {
        $admin = User::factory()->create();
        $owner = Customer::factory()->create(['name' => 'John Owner']);
        $member = Customer::factory()->create([
            'name' => 'Sarah Member',
            'email' => 'sarah.member@example.test',
            'phone' => '+989121111111',
        ]);
        $project = $owner->ensureDefaultProject();
        $allowedVm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'allowed-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
        $blockedVm = VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $project->id,
            'name' => 'blocked-vm',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($admin, 'admin');

        $this->post($this->adminBaseUrl.'/workspaces/'.$project->uuid.'/members', [
            'identifier' => 'sarah.member@example.test',
            'role' => ProjectMember::ROLE_MEMBER,
            'vm_access_scope' => ProjectMember::VM_ACCESS_SPECIFIC,
            'vm_ids' => [$allowedVm->id],
        ])->assertSessionHas('status');

        $memberRow = $project->fresh()->members()->where('customer_id', $member->id)->firstOrFail();

        $this->assertSame(ProjectMember::VM_ACCESS_SPECIFIC, $memberRow->vm_access_scope);
        $this->assertDatabaseHas('project_member_virtual_machines', [
            'project_member_id' => $memberRow->id,
            'virtual_machine_id' => $allowedVm->id,
        ]);
        $this->assertDatabaseMissing('project_member_virtual_machines', [
            'project_member_id' => $memberRow->id,
            'virtual_machine_id' => $blockedVm->id,
        ]);

        $this->patch($this->adminBaseUrl.'/workspaces/'.$project->uuid.'/members/'.$memberRow->id, [
            'role' => ProjectMember::ROLE_VIEWER,
            'vm_access_scope' => ProjectMember::VM_ACCESS_OWN,
        ])->assertSessionHas('status');

        $memberRow = $memberRow->fresh();
        $this->assertSame(ProjectMember::VM_ACCESS_OWN, $memberRow->vm_access_scope);
        $this->assertDatabaseMissing('project_member_virtual_machines', [
            'project_member_id' => $memberRow->id,
            'virtual_machine_id' => $allowedVm->id,
        ]);

        $this->delete($this->adminBaseUrl.'/workspaces/'.$project->uuid.'/members/'.$memberRow->id)
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('project_members', [
            'id' => $memberRow->id,
        ]);
    }

    public function test_admin_workspace_show_does_not_crash_if_specific_vm_pivot_is_missing(): void
    {
        $admin = User::factory()->create();
        $owner = Customer::factory()->create(['name' => 'John Owner']);
        $project = $owner->ensureDefaultProject();

        Schema::dropIfExists('project_member_virtual_machines');

        $this->actingAs($admin, 'admin');

        $this->get($this->adminBaseUrl.'/workspaces/'.$project->uuid)
            ->assertOk()
            ->assertSee('اعضا و دسترسی‌ها');
    }
}
