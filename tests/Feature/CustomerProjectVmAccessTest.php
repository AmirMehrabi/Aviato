<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\Project;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use App\Notifications\WorkspaceAddedNotification;
use App\Services\ProjectAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class CustomerProjectVmAccessTest extends TestCase
{
    use RefreshDatabase;

    private function vm(Project $project, string $name, ?Customer $creator = null): VirtualMachine
    {
        return VirtualMachine::create([
            'project_id' => $project->id,
            'customer_id' => $project->owner_customer_id,
            'created_by_customer_id' => $creator?->id ?? $project->owner_customer_id,
            'name' => $name,
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
    }

    private function membersUrl(Project $project, ?ProjectMember $member = null): string
    {
        return 'https://cp.localhost/projects/'.$project->uuid.'/members'.($member ? '/'.$member->id : '');
    }

    public function test_owner_can_grant_all_specific_and_own_access_and_revoke_direct_access(): void
    {
        $owner = Customer::factory()->create();
        $customer = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $member = $project->members()->create(['customer_id' => $customer->id, 'role' => 'member']);
        $first = $this->vm($project, 'owner-first');
        $second = $this->vm($project, 'owner-second');
        $own = $this->vm($project, 'member-created', $customer);
        $access = app(ProjectAccessService::class);

        $this->actingAs($owner, 'customer')->patch($this->membersUrl($project, $member), [
            'role' => 'member', 'vm_access_scope' => 'all',
        ])->assertSessionHasNoErrors();
        $this->assertEqualsCanonicalizing([$first->id, $second->id, $own->id], $access->visibleVms($project->fresh(), $customer)->pluck('id')->all());
        $future = $this->vm($project, 'future-server');
        $this->assertTrue($access->visibleVms($project->fresh(), $customer)->whereKey($future->id)->exists());
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/servers/'.$second->uuid)->assertOk();

        $this->actingAs($owner, 'customer')->patch($this->membersUrl($project, $member), [
            'role' => 'member', 'vm_access_scope' => 'specific', 'vm_ids' => [$first->id],
        ])->assertSessionHasNoErrors();
        $this->assertSame([$first->id], $access->visibleVms($project->fresh(), $customer)->pluck('id')->all());
        $this->actingAs($customer, 'customer')->get('https://cp.localhost/servers/'.$second->uuid)->assertNotFound();
        $this->get('https://cp.localhost/servers/'.$first->uuid)->assertOk();
        $this->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/servers')->assertOk()->assertSee('owner-first')->assertDontSee('owner-second');
        $later = $this->vm($project, 'later-server');
        $this->assertFalse($access->visibleVms($project->fresh(), $customer)->whereKey($later->id)->exists());

        $this->actingAs($owner, 'customer')->patch($this->membersUrl($project, $member), [
            'role' => 'member', 'vm_access_scope' => 'own',
        ])->assertSessionHasNoErrors();
        $this->assertSame([$own->id], $access->visibleVms($project->fresh(), $customer)->pluck('id')->all());
        $this->assertDatabaseMissing('project_member_virtual_machines', ['project_member_id' => $member->id]);
        $this->assertFalse($member->fresh()->canViewBilling());
        $this->assertFalse($member->fresh()->canReadDns());
    }

    public function test_workspace_admin_can_add_member_with_selected_servers(): void
    {
        Notification::fake();
        $owner = Customer::factory()->create();
        $admin = Customer::factory()->create();
        $customer = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $project->members()->create(['customer_id' => $admin->id, 'role' => 'admin']);
        $vm = $this->vm($project, 'selected-on-invite');

        $this->actingAs($admin, 'customer')->post($this->membersUrl($project), [
            'identifier' => $customer->email,
            'role' => 'viewer',
            'vm_access_scope' => 'specific',
            'vm_ids' => [$vm->id],
        ])->assertSessionHasNoErrors()->assertSessionHas('status');

        $member = $project->members()->where('customer_id', $customer->id)->firstOrFail();
        $this->assertSame('specific', $member->vm_access_scope);
        $this->assertSame([$vm->id], $member->specificVirtualMachines()->pluck('virtual_machines.id')->all());
        $this->assertFalse($member->canManageVms());
        Notification::assertSentTo($customer, WorkspaceAddedNotification::class);
    }

    public function test_invalid_selections_leave_membership_and_assignments_unchanged(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $customer = Customer::factory()->create();
        $member = $project->members()->create(['customer_id' => $customer->id, 'role' => 'member', 'vm_access_scope' => 'specific']);
        $existing = $this->vm($project, 'existing-selection');
        $member->specificVirtualMachines()->attach($existing);
        $foreign = $this->vm($customer->ensureDefaultProject(), 'foreign');
        $deleted = $this->vm($project, 'deleted');
        $deleted->update(['status' => VirtualMachine::STATUS_DELETED]);
        $softDeleted = $this->vm($project, 'soft-deleted');
        $softDeleted->update(['deleted_at' => now()]);

        foreach ([[], [$foreign->id], [$deleted->id], [$softDeleted->id]] as $ids) {
            $this->actingAs($owner, 'customer')->patch($this->membersUrl($project, $member), [
                'role' => 'admin', 'vm_access_scope' => 'specific', 'vm_ids' => $ids,
            ])->assertSessionHasErrors();
            $this->assertSame('member', $member->fresh()->role);
            $this->assertSame([$existing->id], $member->specificVirtualMachines()->pluck('virtual_machines.id')->all());
        }

        $this->patch($this->membersUrl($project, $member), [
            'role' => 'admin', 'vm_access_scope' => 'invalid',
        ])->assertSessionHasErrors('vm_access_scope');
    }

    public function test_ordinary_roles_and_outsiders_cannot_change_access_or_invite_members(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $target = Customer::factory()->create();
        $member = $project->members()->create(['customer_id' => $target->id, 'role' => 'member']);
        foreach (['member', 'viewer', 'billing', 'outsider'] as $role) {
            $actor = Customer::factory()->create();
            if ($role !== 'outsider') {
                $project->members()->create(['customer_id' => $actor->id, 'role' => $role]);
            }
            $this->actingAs($actor, 'customer')->patch($this->membersUrl($project, $member), [
                'role' => 'member', 'vm_access_scope' => 'all',
            ])->assertNotFound();
            $this->post($this->membersUrl($project), [
                'identifier' => $target->email, 'role' => 'member', 'vm_access_scope' => 'all',
            ])->assertNotFound();
        }
        $this->assertSame('own', $member->fresh()->vm_access_scope);
    }

    public function test_owner_access_and_cross_workspace_membership_are_protected(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $ownerMember = $project->members()->where('customer_id', $owner->id)->firstOrFail();
        $this->actingAs($owner, 'customer')->patch($this->membersUrl($project, $ownerMember), [
            'role' => 'member', 'vm_access_scope' => 'own',
        ])->assertUnprocessable();
        $this->post($this->membersUrl($project), [
            'identifier' => $owner->email, 'role' => 'member', 'vm_access_scope' => 'own',
        ])->assertSessionHasErrors('identifier');
        $other = Customer::factory()->create()->ensureDefaultProject()->members()->firstOrFail();
        $this->patch($this->membersUrl($project, $other), [
            'role' => 'member', 'vm_access_scope' => 'all',
        ])->assertNotFound();
        $this->assertSame('all', $ownerMember->fresh()->vm_access_scope);
    }

    public function test_readding_existing_member_without_scope_preserves_selected_assignments(): void
    {
        Notification::fake();
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $customer = Customer::factory()->create();
        $member = $project->members()->create(['customer_id' => $customer->id, 'role' => 'member', 'vm_access_scope' => 'specific']);
        $vm = $this->vm($project, 'keep-selection');
        $member->specificVirtualMachines()->attach($vm);
        $this->actingAs($owner, 'customer')->post($this->membersUrl($project), [
            'identifier' => $customer->email, 'role' => 'viewer',
        ])->assertSessionHasNoErrors();
        $this->assertSame('specific', $member->fresh()->vm_access_scope);
        $this->assertSame([$vm->id], $member->specificVirtualMachines()->pluck('virtual_machines.id')->all());
        Notification::assertNotSentTo($customer, WorkspaceAddedNotification::class);
    }

    public function test_member_editor_only_lists_current_workspace_servers_and_restores_failed_form(): void
    {
        $owner = Customer::factory()->create();
        $project = $owner->ensureDefaultProject();
        $customer = Customer::factory()->create();
        $member = $project->members()->create(['customer_id' => $customer->id, 'role' => 'member']);
        $vm = $this->vm($project, 'available-here');
        $this->vm($customer->ensureDefaultProject(), 'private-other-workspace');
        $deleted = $this->vm($project, 'hidden-deleted-server');
        $deleted->update(['status' => VirtualMachine::STATUS_DELETED]);
        $page = 'https://cp.localhost/projects/'.$project->uuid.'?section=members';

        $this->actingAs($owner, 'customer')->get($page)->assertOk()
            ->assertSee('ویرایش دسترسی')->assertSee('available-here')
            ->assertDontSee('private-other-workspace')->assertDontSee('hidden-deleted-server');
        $this->from($page)->patch($this->membersUrl($project, $member), [
            '_member_form' => 'member-'.$member->id,
            'role' => 'viewer', 'vm_access_scope' => 'specific', 'vm_ids' => [],
        ])->assertRedirect($page);
        $this->get($page)->assertOk()->assertSee('member-'.$member->id)->assertSee(' open ')
            ->assertSee('حداقل یک ماشین برای دسترسی این عضو انتخاب کنید.');
        $this->actingAs($customer, 'customer')->get($page)->assertOk()
            ->assertDontSee('ویرایش دسترسی')->assertDontSee('available-here');
    }
}
