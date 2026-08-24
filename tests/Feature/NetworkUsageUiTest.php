<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NetworkUsageBucket;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmNetworkBillingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkUsageUiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['app.key' => 'base64:'.base64_encode(str_repeat('n', 32))]);
    }

    public function test_admin_can_open_network_dashboard_and_vm_ledger(): void
    {
        $vm = $this->meteredVm();
        $this->period($vm);
        $this->bucket($vm);
        $this->actingAs(User::factory()->create(), 'admin');

        $this->get('https://admin.localhost/billing/network')->assertOk()->assertSee('حسابداری شبکه')->assertSee($vm->display_name);
        $this->get('https://admin.localhost/billing/network/virtual-machines/'.$vm->uuid)->assertOk()->assertSee('دفتر باکت‌ها')->assertSee('bucket-ui-1');
    }

    public function test_customer_sees_network_summary_for_accessible_vm(): void
    {
        $vm = $this->meteredVm();
        $this->period($vm);
        $this->actingAs($vm->customer, 'customer');

        $this->get('https://cp.localhost/network')->assertOk()->assertSee($vm->display_name)->assertSee('900');
        $this->get('https://cp.localhost/servers/'.$vm->uuid.'/network')->assertOk()->assertSee('سهمیه ماهانه');
    }

    public function test_customer_cannot_view_another_projects_vm_network_page(): void
    {
        $vm = $this->meteredVm();
        $other = Customer::factory()->create();
        $this->actingAs($other, 'customer');

        $this->get('https://cp.localhost/servers/'.$vm->uuid.'/network')->assertNotFound();
    }

    private function meteredVm(): VirtualMachine
    {
        $customer = Customer::factory()->create();
        $bundle = VmBundle::query()->create([
            'name' => 'Network UI', 'slug' => 'network-ui', 'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10,
            'monthly_price' => 0, 'network_accounting_enabled' => true,
            'network_included_bytes_monthly' => 1099511627776, 'network_overage_price' => 9000,
            'network_overage_price_unit_bytes' => 1073741824, 'network_usage_direction' => 'both',
            'network_billing_timezone' => 'Asia/Tehran',
        ]);

        return VirtualMachine::query()->create([
            'customer_id' => $customer->id, 'vm_bundle_id' => $bundle->id, 'name' => 'network-ui-vm',
            'display_name' => 'Network UI VM', 'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10,
            'status' => VirtualMachine::STATUS_RUNNING, 'provisioning_status' => VirtualMachine::PROVISION_READY,
        ])->load(['customer', 'project.owner', 'bundle']);
    }

    private function period(VirtualMachine $vm): VmNetworkBillingPeriod
    {
        return VmNetworkBillingPeriod::query()->create([
            'virtual_machine_id' => $vm->id, 'customer_id' => $vm->customer_id, 'project_id' => $vm->project_id,
            'period_start' => now()->startOfMonth(), 'period_end' => now()->addMonth()->startOfMonth(),
            'timezone' => 'Asia/Tehran', 'direction' => 'both', 'included_bytes' => 1099511627776,
            'price_per_unit' => 9000, 'price_unit_bytes' => 1073741824, 'currency' => 'IRR',
            'policy_snapshot' => ['direction' => 'both'], 'ingress_bytes' => 1099511627776,
            'egress_bytes' => 1073741824, 'rated_bytes' => 1100585369600, 'billable_bytes' => 1073741824,
            'accrued_amount' => 9000,
        ]);
    }

    private function bucket(VirtualMachine $vm): void
    {
        NetworkUsageBucket::query()->create([
            'source' => 'test', 'bucket_id' => 'bucket-ui-1', 'revision' => 1, 'status' => 'final',
            'virtual_machine_id' => $vm->id, 'vm_uuid' => $vm->uuid, 'assignment_id' => 'assignment-ui-1',
            'interval_start' => now()->subHour(), 'interval_end' => now(), 'ingress_bytes' => 100,
            'egress_bytes' => 200, 'completeness' => 'complete', 'calculation_version' => 'test',
            'finalized_at' => now(), 'source_updated_at' => now(), 'payload_hash' => str_repeat('a', 64),
            'payload' => [], 'processing_status' => 'rated', 'rated_at' => now(),
        ]);
    }
}
