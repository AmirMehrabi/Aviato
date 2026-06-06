<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\ProjectMember;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
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
        $this->get($this->customerBaseUrl.'/servers/'.$vm->uuid)->assertOk();

        $this->actingAs($outsider, 'customer');
        $this->get($this->customerBaseUrl.'/servers/'.$vm->uuid)->assertNotFound();
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

        app(UsageBillingService::class)->chargeVm($vm);

        $this->assertSame(-3000, $owner->wallet()->firstOrFail()->balance);
        $this->assertSame(0, $member->wallet()->firstOrFail()->balance);
        $transaction = $owner->walletTransactions()->firstOrFail();
        $this->assertSame($project->id, $transaction->metadata['project_id']);
        $this->assertSame($member->id, $transaction->metadata['created_by_customer_id']);

        CarbonImmutable::setTestNow();
    }
}
