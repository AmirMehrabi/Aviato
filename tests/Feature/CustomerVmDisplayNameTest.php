<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CustomerVmDisplayNameTest extends TestCase
{
    use RefreshDatabase;

    private string $base = 'https://cp.localhost';

    public function test_owner_can_rename_vm_without_changing_infrastructure_names(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->createVm($owner);
        $this->actingAs($owner, 'customer');

        $this->patch($this->base.'/servers/'.$vm->uuid.'/display-name', [
            'display_name' => '  پایگاه داده Production  ',
            'name' => 'changed-internal-name',
            'hostname' => 'changed-hostname',
        ])->assertRedirect($this->base.'/servers/'.$vm->uuid)
            ->assertSessionHas('status', 'نام نمایشی به‌روزرسانی شد');

        $vm->refresh();
        $this->assertSame('پایگاه داده Production', $vm->display_name);
        $this->assertSame('internal-vm', $vm->name);
        $this->assertSame('internal-vm.example.test', $vm->hostname);
        $this->assertDatabaseHas('vm_activities', [
            'virtual_machine_id' => $vm->id,
            'actor_customer_id' => $owner->id,
            'event' => 'rename',
        ]);

        $this->get($this->base.'/servers/'.$vm->uuid)->assertOk()
            ->assertSee('پایگاه داده Production')->assertSee('ویرایش نام نمایشی');
        $this->get($this->base.'/dashboard')->assertOk()->assertSee('پایگاه داده Production');
        $this->get($this->base.'/servers/'.$vm->uuid.'/delete')->assertOk()->assertSee('پایگاه داده Production');
        $this->get($this->base.'/servers/'.$vm->uuid.'/rebuild')->assertOk()->assertSee('پایگاه داده Production');
    }

    public function test_invalid_names_are_rejected_and_editor_preserves_input(): void
    {
        $owner = Customer::factory()->create();
        $vm = $this->createVm($owner);
        $this->actingAs($owner, 'customer');
        $url = $this->base.'/servers/'.$vm->uuid;

        foreach (['', '   ', str_repeat('ن', 129), ['invalid']] as $name) {
            $this->from($url)->patch($url.'/display-name', ['display_name' => $name])
                ->assertRedirect($url)->assertSessionHasErrors('display_name', null, 'renameVm');
            $page = $this->withCookie(config('session.cookie'), session()->getId())
                ->get($url)->assertOk()->assertSee('vm-display-name-error', false);
            if (is_string($name)) {
                $page->assertSee('value="'.e(trim($name)).'"', false);
            }
            $this->assertSame('Original Name', $vm->fresh()->display_name);
        }

        $name = str_repeat('ن', 128);
        $this->patch($url.'/display-name', ['display_name' => $name])->assertSessionHasNoErrors();
        $this->assertSame($name, $vm->fresh()->display_name);
    }

    public function test_viewer_and_unrelated_customer_cannot_rename_vm(): void
    {
        $owner = Customer::factory()->create();
        $viewer = Customer::factory()->create();
        $stranger = Customer::factory()->create();
        $vm = $this->createVm($owner);
        $vm->project->members()->create(['customer_id' => $viewer->id, 'role' => ProjectMember::ROLE_VIEWER]);
        $url = $this->base.'/servers/'.$vm->uuid;

        $this->actingAs($viewer, 'customer')->get($url)->assertOk()->assertDontSee('ویرایش نام نمایشی');
        foreach ([$viewer, $stranger] as $customer) {
            $this->actingAs($customer, 'customer')->patch($url.'/display-name', ['display_name' => 'Denied'])
                ->assertNotFound();
        }
        $this->assertSame('Original Name', $vm->fresh()->display_name);
    }

    public function test_admin_can_rename_and_member_cannot_rename_another_members_vm(): void
    {
        $owner = Customer::factory()->create();
        $admin = Customer::factory()->create();
        $member = Customer::factory()->create();
        $vm = $this->createVm($owner);
        $project = $vm->project;
        $project->members()->create(['customer_id' => $admin->id, 'role' => ProjectMember::ROLE_ADMIN]);
        $project->members()->create(['customer_id' => $member->id, 'role' => ProjectMember::ROLE_MEMBER]);
        $url = $this->base.'/servers/'.$vm->uuid.'/display-name';

        $this->actingAs($admin, 'customer')->patch($url, ['display_name' => 'Admin Name'])->assertRedirect();
        $this->actingAs($member, 'customer')->patch($url, ['display_name' => 'Denied'])->assertNotFound();
        $vm->update(['created_by_customer_id' => $member->id]);
        $this->patch($url, ['display_name' => 'Member Name'])->assertRedirect();
        $this->assertSame('Member Name', $vm->fresh()->display_name);
    }

    public function test_search_matches_display_name_and_keeps_workspace_scope(): void
    {
        $owner = Customer::factory()->create();
        $other = Customer::factory()->create();
        $vm = $this->createVm($owner);
        $vm->update(['display_name' => 'Production Database']);
        $otherVm = $this->createVm($other);
        $otherVm->update(['display_name' => 'Production Secret']);

        $this->actingAs($owner, 'customer')->get($this->base.'/servers?search=Production')
            ->assertOk()->assertViewHas('servers', fn ($servers) => $servers->count() === 1 && $servers->first()->id === $vm->id)
            ->assertSee('Production Database')->assertDontSee('Production Secret');
    }

    private function createVm(Customer $owner): VirtualMachine
    {
        return VirtualMachine::create([
            'customer_id' => $owner->id,
            'project_id' => $owner->ensureDefaultProject()->id,
            'created_by_customer_id' => $owner->id,
            'name' => 'internal-vm',
            'display_name' => 'Original Name',
            'hostname' => 'internal-vm.example.test',
            'cpu_cores' => 2,
            'ram_gb' => 4,
            'disk_gb' => 80,
            'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
    }
}
