<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NetworkUsageBucket;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\NetworkMonthlyEstimateService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class NetworkMonthlyEstimateTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::parse('2026-10-17 12:00:00', 'Asia/Tehran'));
    }

    public function test_projection_uses_seven_complete_local_days_direction_and_monthly_allowance(): void
    {
        $vm = $this->networkVm();
        foreach (range(1, 7) as $daysAgo) {
            $this->day($vm, $daysAgo, 2 * 1073741824);
        }
        $this->day($vm, 8, 100 * 1073741824);
        $this->day($vm, 0, 100 * 1073741824);
        $estimate = app(NetworkMonthlyEstimateService::class)->forOwner($vm->customer);

        // 2 GiB/day * 31 days, less 10 GiB included, at 9,000 IRR/GiB.
        $this->assertSame(['amount' => 468000, 'pending' => false, 'included' => true], $estimate);
        $this->assertSame(500000, app(NetworkMonthlyEstimateService::class)->roundedTotal($estimate['amount']));
    }

    public function test_estimate_stays_fixed_during_day_and_refreshes_the_next_day(): void
    {
        $vm = $this->networkVm();
        $bucket = $this->day($vm, 1, 2 * 1073741824);
        $service = app(NetworkMonthlyEstimateService::class);
        $before = $service->forOwner($vm->customer);
        $bucket->update(['egress_bytes' => 4 * 1073741824]);
        $this->travelTo(now()->setTime(23, 59));
        $this->assertSame($before, $service->forOwner($vm->customer));

        $this->travelTo(now()->addDay()->setTime(0, 1));
        $this->assertGreaterThan($before['amount'], $service->forOwner($vm->customer)['amount']);
    }

    public function test_missing_or_partial_days_are_not_treated_as_zero_usage(): void
    {
        $vm = $this->networkVm();
        $bucket = $this->day($vm, 1, 1073741824);
        $bucket->update(['interval_end' => $bucket->interval_end->subHours(12)]);
        $this->day($vm, 2, 1073741824)->update(['completeness' => 'partial']);
        $this->day($vm, 3, 1073741824)->update(['status' => 'provisional']);

        $this->assertSame(['amount' => 0, 'pending' => true, 'included' => true], app(NetworkMonthlyEstimateService::class)->forOwner($vm->customer));
        $this->actingAs($vm->customer, 'customer')->get('https://cp.localhost/wallet')
            ->assertOk()->assertSee('در حال محاسبه برآورد شبکه');
    }

    public function test_new_vm_uses_available_full_days_and_wallet_shows_rounded_network_total(): void
    {
        $vm = $this->networkVm();
        $vm->forceFill(['created_at' => now()->subDays(2)])->save();
        $this->day($vm, 1, 2 * 1073741824);
        $this->actingAs($vm->customer, 'customer')->get('https://cp.localhost/wallet')
            ->assertOk()->assertViewHas('networkEstimate', ['amount' => 468000, 'pending' => false, 'included' => true])
            ->assertViewHas('monthlyEstimate', 500000)
            ->assertSee('روزانه به‌روزرسانی می‌شود')
            ->assertDontSee('کارکرد تسویه‌نشده')
            ->assertDontSee('موجودی ثبت‌شده');
        $this->assertDatabaseCount('wallet_transactions', 0);
        $this->assertDatabaseCount('usage_accruals', 0);
    }

    public function test_disabled_network_and_other_customers_are_excluded(): void
    {
        $vm = $this->networkVm();
        $this->day($vm, 1, 2 * 1073741824);
        $other = Customer::factory()->create();
        $this->assertSame(['amount' => 0, 'pending' => false, 'included' => false], app(NetworkMonthlyEstimateService::class)->forOwner($other));
        $vm->update(['network_accounting_enabled_override' => false]);
        $this->assertSame(['amount' => 0, 'pending' => false, 'included' => false], app(NetworkMonthlyEstimateService::class)->forOwner($vm->customer));
    }

    public function test_shared_wallet_sums_each_vm_after_its_own_allowance_and_scopes_workspace_total(): void
    {
        $first = $this->networkVm();
        $otherProject = $first->customer->ownedProjects()->create(['name' => 'Other']);
        $second = $first->replicate(['uuid']);
        $second->project_id = $otherProject->id;
        $second->save();
        $second->forceFill(['created_at' => now()->subDays(10)])->save();
        $this->day($first, 1, 2 * 1073741824);
        $this->day($second, 1, 1073741824);
        $service = app(NetworkMonthlyEstimateService::class);

        $this->assertSame(657000, $service->forOwner($first->customer)['amount']);
        $this->assertSame(468000, $service->forOwner($first->customer, $first->project)['amount']);
        $this->assertSame(189000, $service->forOwner($first->customer, $otherProject)['amount']);
    }

    public function test_pending_estimate_is_also_stable_when_late_data_arrives(): void
    {
        $vm = $this->networkVm();
        $service = app(NetworkMonthlyEstimateService::class);
        $this->assertTrue($service->forOwner($vm->customer)['pending']);
        $this->day($vm, 1, 2 * 1073741824);
        $this->assertTrue($service->forOwner($vm->customer)['pending']);
        $this->travelTo(now()->addDay());
        $this->assertFalse($service->forOwner($vm->customer)['pending']);
    }

    private function networkVm(): VirtualMachine
    {
        $owner = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Network', 'slug' => 'network', 'cpu_cores' => 1, 'ram_gb' => 1,
            'disk_gb' => 10, 'ip_count' => 1, 'monthly_price' => 0, 'is_active' => true,
            'network_accounting_enabled' => true, 'network_included_bytes_monthly' => 10 * 1073741824,
            'network_overage_price' => 9000, 'network_overage_price_unit_bytes' => 1073741824,
            'network_usage_direction' => 'egress', 'network_billing_timezone' => 'Asia/Tehran',
        ]);

        $vm = VirtualMachine::create([
            'customer_id' => $owner->id, 'project_id' => $owner->ensureDefaultProject()->id,
            'vm_bundle_id' => $bundle->id, 'name' => 'network-vm', 'cpu_cores' => 1,
            'ram_gb' => 1, 'disk_gb' => 10, 'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);
        $vm->forceFill(['created_at' => now()->subDays(10)])->save();

        return $vm;
    }

    private function day(VirtualMachine $vm, int $daysAgo, int $egress): NetworkUsageBucket
    {
        $start = CarbonImmutable::now('Asia/Tehran')->startOfDay()->subDays($daysAgo);

        return NetworkUsageBucket::create([
            'source' => 'test', 'bucket_id' => 'vm-'.$vm->id.'-day-'.$daysAgo, 'revision' => 1, 'status' => 'final',
            'virtual_machine_id' => $vm->id, 'vm_uuid' => $vm->uuid, 'assignment_id' => 'test-assignment',
            'interval_start' => $start->utc(), 'interval_end' => $start->addDay()->utc(),
            'ingress_bytes' => 1073741824, 'egress_bytes' => $egress, 'completeness' => 'complete',
            'source_updated_at' => now(), 'calculation_version' => 'test', 'payload_hash' => hash('sha256', (string) $daysAgo), 'payload' => [],
        ]);
    }
}
