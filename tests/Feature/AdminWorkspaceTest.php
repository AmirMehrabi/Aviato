<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\VirtualMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSee('Workspaces')
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
            ->assertSee('Billing owner');
    }
}
