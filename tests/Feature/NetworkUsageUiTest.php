<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\NetworkUsageBucket;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmNetworkBillingPeriod;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
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

    public function test_vm_detail_includes_dedicated_network_tab_and_usage_ledger(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
        $vm = $this->meteredVm();
        $this->period($vm);
        $this->bucket($vm);
        $this->actingAs(User::factory()->create(), 'admin');
        $this->get('https://admin.localhost/virtual-machines/'.$vm->uuid)
            ->assertOk()->assertSee("id:'network'", false)->assertSee('پیشرفت سهمیه')
            ->assertSee('مصرف روزانه')->assertSee('نمودار ترافیک ورودی و خروجی')->assertSee('bucket-ui-1')
            ->assertSee('vmNetworkChart')->assertSee('۱۴۰۵/۰۸/۲۷');
    }

    public function test_network_tab_requires_network_read_but_not_billing_read(): void
    {
        $vm = $this->meteredVm();
        $this->period($vm);
        $admin = User::factory()->create(['role' => AdminRole::Custom, 'permissions' => ['virtual-machines.read']]);
        $url = 'https://admin.localhost/virtual-machines/'.$vm->uuid;
        $this->actingAs($admin, 'admin')->get($url)->assertOk()->assertViewHas('networkUsage', null);
        $admin->update(['permissions' => ['virtual-machines.read', 'network.read']]);
        $this->get($url)->assertOk()->assertSee("id:'network'", false)->assertSee('پیشرفت سهمیه')->assertDontSee('هزینه دوره');
    }

    public function test_admin_network_dashboard_lists_machine_without_billing_period(): void
    {
        $vm = $this->meteredVm();
        $this->actingAs(User::factory()->create(), 'admin');

        $this->get('https://admin.localhost/billing/network')
            ->assertOk()
            ->assertSee($vm->display_name)
            ->assertSee('بدون داده');
    }

    public function test_admin_can_sort_network_usage_across_pages_and_save_preference(): void
    {
        $vm = $this->meteredVm();
        $period = $this->period($vm);
        $ids = [$vm->id];
        for ($i = 1; $i <= 16; $i++) {
            $machine = $vm->replicate(['uuid']);
            $machine->display_name = 'Sorted machine '.$i;
            $machine->save();
            $ids[] = $machine->id;
            $latest = $period->replicate();
            $latest->virtual_machine_id = $machine->id;
            $latest->rated_bytes = $period->rated_bytes + $i;
            $latest->save();
            $older = $latest->replicate();
            $older->period_start = now()->subMonth()->startOfMonth();
            $older->period_end = now()->startOfMonth();
            $older->rated_bytes = PHP_INT_MAX - $i;
            $older->save();
        }
        $withoutData = $vm->replicate(['uuid']);
        $withoutData->save();
        $this->actingAs(User::factory()->create(), 'admin');

        $this->get('https://admin.localhost/billing/network?sort=rated_bytes&direction=desc')
            ->assertOk()
            ->assertSee('data-sort-column="rated_bytes"', false)
            ->assertViewHas('machines', fn ($machines) => $machines->pluck('id')->all() === array_slice(array_reverse($ids), 0, 15));
        $this->get('https://admin.localhost/billing/network?sort=rated_bytes&direction=desc&page=2')
            ->assertOk()
            ->assertViewHas('machines', fn ($machines) => $machines->pluck('id')->all() === [$ids[1], $ids[0], $withoutData->id]);
        $this->get('https://admin.localhost/billing/network?sort=rated_bytes&direction=asc')
            ->assertOk()
            ->assertViewHas('machines', fn ($machines) => $machines->pluck('id')->all() === array_merge([$withoutData->id], array_slice($ids, 0, 14)));

        $this->putJson('https://admin.localhost/table-preferences/billing-network', [
            'column' => 'rated_bytes', 'direction' => 'desc',
        ])->assertOk();
        $this->get('https://admin.localhost/billing/network')
            ->assertOk()
            ->assertViewHas('sort', fn ($sort) => $sort['column'] === 'rated_bytes' && $sort['source'] === 'preference')
            ->assertViewHas('machines', fn ($machines) => $machines->pluck('id')->all() === array_slice(array_reverse($ids), 0, 15));
    }

    public function test_customer_sees_network_summary_for_accessible_vm(): void
    {
        $this->travelTo(Carbon::parse('2026-11-18 12:00:00'));
        $vm = $this->meteredVm();
        $this->period($vm);
        $this->actingAs($vm->customer, 'customer');

        $url = 'https://cp.localhost/servers/'.$vm->uuid.'/network';
        $this->get('https://cp.localhost/network')->assertOk()->assertSee($vm->display_name)->assertSee('900');
        $this->get($url)->assertOk()->assertSee('سهمیه ماهانه')
            ->assertSee('مصرف روزانه نهایی‌شده موجود نیست.')
            ->assertDontSee('vmNetworkChart');

        $this->bucket($vm);
        $this->get($url)->assertOk()->assertViewIs('customer.servers.detail')
            ->assertViewHas('tab', 'network')
            ->assertViewHas('daily', fn ($daily) => $daily->count() === 1
                && (int) $daily->first()->ingress_bytes === 100
                && (int) $daily->first()->egress_bytes === 200)
            ->assertSee('نمودار ترافیک ورودی و خروجی')
            ->assertSee('vmNetworkChart')
            ->assertSee('۱۴۰۵/۰۸/۲۷')
            ->assertSee('جزئیات مصرف روزانه');
    }

    public function test_customer_can_switch_between_jalali_traffic_months(): void
    {
        $this->travelTo(Carbon::parse('2026-03-23 12:00:00', 'Asia/Tehran'));
        $vm = $this->meteredVm();
        $this->period($vm);
        $this->actingAs($vm->customer, 'customer');

        $newYear = Carbon::parse('2026-03-21 00:00:00', 'Asia/Tehran');
        $this->bucket($vm, [
            'interval_start' => $newYear->copy()->subHour(), 'interval_end' => $newYear,
            'ingress_bytes' => 4_500_000_000, 'egress_bytes' => 5_000_000_000,
        ]);
        $this->bucket($vm, [
            'bucket_id' => 'bucket-ui-2',
            'interval_start' => $newYear, 'interval_end' => $newYear->copy()->addHour(),
            'ingress_bytes' => 1_500_000_000, 'egress_bytes' => 2_250_000_000,
        ]);
        $this->bucket($vm, [
            'bucket_id' => 'bucket-ui-3',
            'interval_start' => $newYear->copy()->addHours(4), 'interval_end' => $newYear->copy()->addHours(5),
            'ingress_bytes' => 750_000_000, 'egress_bytes' => 0,
        ]);

        $url = 'https://cp.localhost/servers/'.$vm->uuid.'/network';
        $this->get($url)
            ->assertOk()
            ->assertViewHas('selectedMonth', '1405-01')
            ->assertViewHas('chartStart', '2026-03-21')
            ->assertViewHas('chartEnd', '2026-04-20')
            ->assertViewHas('daily', fn ($daily) => $daily->pluck('usage_date')->all() === ['2026-03-21']
                && $daily->first()->ingress_bytes === 2_250_000_000)
            ->assertSee('value="1404-12"', false)
            ->assertSee('ورودی (GB)')
            ->assertSee('خروجی (GB)')
            ->assertSee('>2.250</bdi>', false);

        $this->get($url.'?month=1404-12')
            ->assertOk()
            ->assertViewHas('selectedMonth', '1404-12')
            ->assertViewHas('chartEnd', '2026-03-20')
            ->assertViewHas('daily', fn ($daily) => $daily->pluck('usage_date')->all() === ['2026-03-20']
                && $daily->first()->ingress_bytes === 4_500_000_000)
            ->assertSee('۱۴۰۴/۱۲/۲۹')
            ->assertSee('>4.500</bdi>', false);

        $this->get($url.'?month=1405-02')->assertNotFound();
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

    private function bucket(VirtualMachine $vm, array $attributes = []): void
    {
        NetworkUsageBucket::query()->create($attributes + [
            'source' => 'test', 'bucket_id' => 'bucket-ui-1', 'revision' => 1, 'status' => 'final',
            'virtual_machine_id' => $vm->id, 'vm_uuid' => $vm->uuid, 'assignment_id' => 'assignment-ui-1',
            'interval_start' => now()->subHour(), 'interval_end' => now(), 'ingress_bytes' => 100,
            'egress_bytes' => 200, 'completeness' => 'complete', 'calculation_version' => 'test',
            'finalized_at' => now(), 'source_updated_at' => now(), 'payload_hash' => str_repeat('a', 64),
            'payload' => [], 'processing_status' => 'rated', 'rated_at' => now(),
        ]);
    }
}
