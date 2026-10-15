<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\NetworkBillingPolicyAdjustment;
use App\Models\NetworkUsageRating;
use App\Models\ProxmoxServer;
use App\Models\UsageAccrual;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Models\VmNetworkBillingPeriod;
use App\Services\NetworkBillingPolicyAdjustmentService;
use App\Services\NetworkBillingPolicyService;
use App\Services\NetworkUsageIngestionService;
use App\Services\NetworkUsageRatingService;
use App\Services\UsageBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class NetworkUsageBillingTest extends TestCase
{
    use RefreshDatabase;

    public function test_final_bucket_over_allowance_is_rated_once_in_irr(): void
    {
        $vm = $this->networkVm();
        $payload = $this->payload($vm, bytes: 1099511627776 + 1073741824);
        $ingestion = app(NetworkUsageIngestionService::class);

        $bucket = $ingestion->ingest('test-ipdr', $payload);
        $this->assertTrue(app(NetworkUsageRatingService::class)->rate($bucket));
        $this->assertNull($ingestion->ingest('test-ipdr', $payload));

        $this->assertDatabaseCount('network_usage_buckets', 1);
        $this->assertDatabaseCount('network_usage_ratings', 1);
        $this->assertDatabaseHas('usage_accruals', [
            'category' => UsageAccrual::CATEGORY_NETWORK,
            'amount' => 9000,
        ]);
    }

    public function test_higher_revision_recalculates_pending_accrual_without_double_charge(): void
    {
        $vm = $this->networkVm();
        $ingestion = app(NetworkUsageIngestionService::class);
        $rating = app(NetworkUsageRatingService::class);
        $bucket = $ingestion->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824));
        $rating->rate($bucket);

        $corrected = $this->payload($vm, 1099511627776, revision: 2);
        $bucket = $ingestion->ingest('test-ipdr', $corrected);
        $rating->rate($bucket);

        $this->assertSame(0, (int) UsageAccrual::query()->where('category', UsageAccrual::CATEGORY_NETWORK)->value('amount'));
        $this->assertSame(-9000, (int) NetworkUsageRating::query()->where('revision', 2)->value('amount_delta'));
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_same_revision_with_different_content_is_rejected(): void
    {
        $vm = $this->networkVm();
        $ingestion = app(NetworkUsageIngestionService::class);
        $ingestion->ingest('test-ipdr', $this->payload($vm, 100));

        $this->expectException(RuntimeException::class);
        $ingestion->ingest('test-ipdr', $this->payload($vm, 101));
    }

    public function test_assignment_id_cannot_move_to_another_vm(): void
    {
        $first = $this->networkVm();
        $second = $this->networkVm('metered-2');
        $ingestion = app(NetworkUsageIngestionService::class);
        $ingestion->ingest('test-ipdr', $this->payload($first, 100));
        $payload = $this->payload($second, 100);
        $payload['bucket_id'] = 'bucket-2';

        $this->expectException(RuntimeException::class);
        $ingestion->ingest('test-ipdr', $payload);
    }

    public function test_utc_bucket_at_tehran_month_boundary_is_rated_in_the_new_month(): void
    {
        config(['app.timezone' => 'Asia/Tehran']);
        $vm = $this->networkVm();
        $payload = array_replace($this->payload($vm, 1073741824), [
            'interval_start' => '2026-09-30T20:30:00Z',
            'interval_end' => '2026-09-30T21:00:00Z',
        ]);
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $payload)->fresh();
        $this->assertSame('UTC', $bucket->interval_start->timezoneName);
        $this->assertSame('2026-10-01 00:00:00', $bucket->interval_start->copy()->setTimezone('Asia/Tehran')->format('Y-m-d H:i:s'));
        $rating = app(NetworkUsageRatingService::class);
        $this->assertTrue($rating->rate($bucket));
        $period = VmNetworkBillingPeriod::first()->fresh();
        $this->assertSame('2026-09-30 20:30:00', $period->period_start->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $period->period_start->timezoneName);
        $this->assertSame('rated', $bucket->fresh()->processing_status);
        $this->assertFalse($rating->rate($bucket->fresh()));
    }

    public function test_bucket_ending_at_tehran_month_boundary_stays_in_previous_month(): void
    {
        config(['app.timezone' => 'Asia/Tehran']);
        $vm = $this->networkVm();
        $payload = array_replace($this->payload($vm, 1073741824), [
            'interval_start' => '2026-09-30T20:00:00Z',
            'interval_end' => '2026-09-30T20:30:00Z',
        ]);
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $payload)->fresh();
        $this->assertTrue(app(NetworkUsageRatingService::class)->rate($bucket));
        $this->assertDatabaseHas('vm_network_billing_periods', ['period_start' => '2026-08-31 20:30:00', 'period_end' => '2026-09-30 20:30:00']);
    }

    public function test_vm_overrides_control_actual_rating_including_unit_size_quota_and_direction(): void
    {
        $vm = $this->networkVm();
        $vm->update([
            'network_overage_price_override' => 6500,
            'network_overage_price_unit_bytes_override' => 2147483648,
            'network_included_bytes_monthly_override' => 0,
            'network_usage_direction_override' => 'ingress',
        ]);
        $payload = $this->payload($vm, 99 * 1073741824);
        $payload['ingress_bytes'] = 3 * 1073741824;
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $payload);
        app(NetworkUsageRatingService::class)->rate($bucket);

        $this->assertSame(9750, VmNetworkBillingPeriod::first()->accrued_amount);
        $this->assertSame(9750, UsageAccrual::first()->amount);
        $this->assertSame(6500, NetworkUsageRating::first()->policy_snapshot['price_per_unit']);
    }

    public function test_edit_form_saves_override_and_reprices_pending_usage_and_customer_page(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15T12:00:00Z'));
        $vm = $this->networkVm();
        $server = ProxmoxServer::create(['name' => 'Test node', 'host' => 'pve.test', 'username' => 'root', 'realm' => 'pam']);
        $vm->update(['proxmox_server_id' => $server->id]);
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824));
        app(NetworkUsageRatingService::class)->rate($bucket);
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->put('https://admin.localhost/virtual-machines/'.$vm->uuid, [
            'customer_id' => $vm->customer_id, 'proxmox_server_id' => $server->id,
            'vm_bundle_id' => $vm->vm_bundle_id, 'name' => $vm->name,
            'network_overage_price_override' => 6500, 'network_overage_price_unit_bytes_override' => 1073741824,
            'sync_to_proxmox' => false,
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame(6500, $vm->fresh()->network_overage_price_override);
        $this->assertSame(6500, VmNetworkBillingPeriod::first()->accrued_amount);
        $this->assertSame(6500, UsageAccrual::first()->amount);
        $this->assertSame(-2500, NetworkBillingPolicyAdjustment::first()->amount_delta);
        $this->assertSame(6500, UsageAccrual::first()->snapshot['price_per_unit']);
        $this->assertDatabaseCount('wallet_transactions', 0);
        // The old rating remains an audit record of the original calculation.
        $this->assertSame(9000, NetworkUsageRating::first()->amount_delta);
        $this->actingAs($vm->customer, 'customer')->get('https://cp.localhost/servers/'.$vm->uuid.'/network')
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['accrued_amount'] === 6500);

        app(NetworkBillingPolicyAdjustmentService::class)->refresh($vm->fresh());
        $this->assertDatabaseCount('network_billing_policy_adjustments', 1);
        $this->assertSame(6500, UsageAccrual::first()->amount);
    }

    public function test_subsequent_bucket_uses_updated_vm_rate_and_does_not_charge_correction_twice(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15T12:00:00Z'));
        $vm = $this->networkVm();
        $ingestion = app(NetworkUsageIngestionService::class);
        $rating = app(NetworkUsageRatingService::class);
        $rating->rate($ingestion->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824)));
        $vm->update(['network_overage_price_override' => 6500]);
        $payload = $this->payload($vm, 1073741824);
        $payload['bucket_id'] = 'bucket-next';
        $payload['interval_start'] = '2026-08-15T09:00:00Z';
        $payload['interval_end'] = '2026-08-15T10:00:00Z';
        $next = $ingestion->ingest('test-ipdr', $payload);
        $this->assertTrue($rating->rate($next));
        $this->assertFalse($rating->rate($next));
        $this->assertSame(13000, VmNetworkBillingPeriod::first()->accrued_amount);
        $this->assertSame(13000, (int) UsageAccrual::sum('amount'));
        $this->assertSame(6500, NetworkUsageRating::where('network_usage_bucket_id', $next->id)->first()->amount_delta);
        $this->assertDatabaseCount('network_billing_policy_adjustments', 1);
    }

    public function test_settled_usage_is_preserved_and_policy_reduction_is_refunded_once(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15T12:00:00Z'));
        $vm = $this->networkVm();
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824));
        app(NetworkUsageRatingService::class)->rate($bucket);
        app(UsageBillingService::class)->settleDate('2026-08-15');
        $vm->update(['network_overage_price_override' => 6500]);
        $adjustments = app(NetworkBillingPolicyAdjustmentService::class);
        $adjustments->refresh($vm);
        $adjustments->refresh($vm);

        $this->assertSame(9000, UsageAccrual::first()->amount);
        $this->assertNotNull(UsageAccrual::first()->settled_at);
        $this->assertSame(6500, VmNetworkBillingPeriod::first()->accrued_amount);
        $this->assertSame(-6500, $vm->customer->wallet->fresh()->balance);
        $this->assertDatabaseCount('wallet_transactions', 2);
        $this->assertNotNull(NetworkBillingPolicyAdjustment::first()->wallet_transaction_id);
    }

    public function test_past_period_keeps_historical_rate_and_zero_override_does_not_fall_back_to_bundle(): void
    {
        $vm = $this->networkVm();
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824));
        app(NetworkUsageRatingService::class)->rate($bucket);
        $vm->update(['network_overage_price_override' => 0, 'network_accounting_enabled_override' => false]);
        app(NetworkBillingPolicyAdjustmentService::class)->refresh($vm);
        $policy = app(NetworkBillingPolicyService::class)->effective($vm);
        $this->assertSame(0, $policy['price_per_unit']);
        $this->assertFalse($policy['enabled']);
        $this->assertSame(9000, VmNetworkBillingPeriod::first()->price_per_unit);
        $this->assertDatabaseCount('network_billing_policy_adjustments', 0);
    }

    public function test_increased_price_accrues_difference_and_later_reduction_clears_it(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15T12:00:00Z'));
        $vm = $this->networkVm();
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824));
        app(NetworkUsageRatingService::class)->rate($bucket);
        $adjustments = app(NetworkBillingPolicyAdjustmentService::class);
        $vm->update(['network_overage_price_override' => 12000]);
        $adjustments->refresh($vm);
        $this->assertSame(12000, (int) UsageAccrual::sum('amount'));
        $this->assertNotNull(NetworkBillingPolicyAdjustment::first()->usage_accrual_id);
        $vm->update(['network_overage_price_override' => 0]);
        $adjustments->refresh($vm);
        $this->assertSame(0, (int) UsageAccrual::sum('amount'));
        $this->assertSame(0, VmNetworkBillingPeriod::first()->accrued_amount);
        $this->assertDatabaseCount('wallet_transactions', 0);
    }

    public function test_repair_command_previews_without_writes_then_applies_idempotently(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-08-15T12:00:00Z'));
        $vm = $this->networkVm();
        $bucket = app(NetworkUsageIngestionService::class)->ingest('test-ipdr', $this->payload($vm, 1099511627776 + 1073741824));
        app(NetworkUsageRatingService::class)->rate($bucket);
        $this->artisan('network:refresh-vm-policy', ['vm' => $vm->uuid, '--price' => '6500'])->assertSuccessful();
        $this->assertNull($vm->fresh()->network_overage_price_override);
        $this->assertSame(9000, UsageAccrual::first()->amount);
        foreach (range(1, 2) as $attempt) {
            $this->artisan('network:refresh-vm-policy', ['vm' => $vm->uuid, '--price' => '6500', '--apply' => true])->assertSuccessful();
        }
        $this->assertSame(6500, UsageAccrual::first()->amount);
        $this->assertDatabaseCount('network_billing_policy_adjustments', 1);
    }

    public function test_non_pricing_admin_cannot_change_network_cost_inputs(): void
    {
        $vm = $this->networkVm();
        $admin = User::factory()->create(['role' => AdminRole::Infrastructure]);
        foreach (['network_overage_price_override' => 6500, 'network_included_bytes_monthly_override' => 0, 'network_usage_direction_override' => 'ingress'] as $field => $value) {
            $this->actingAs($admin, 'admin')->put('https://admin.localhost/virtual-machines/'.$vm->uuid, [$field => $value])
                ->assertForbidden();
        }
        $this->assertNull($vm->fresh()->network_overage_price_override);
    }

    private function networkVm(string $slug = 'metered'): VirtualMachine
    {
        $customer = Customer::factory()->create();
        $bundle = VmBundle::query()->create([
            'name' => 'Metered', 'slug' => $slug, 'cpu_cores' => 1, 'ram_gb' => 1,
            'disk_gb' => 10, 'ip_count' => 1, 'monthly_price' => 0, 'is_active' => true,
            'network_accounting_enabled' => true, 'network_included_bytes_monthly' => 1099511627776,
            'network_overage_price' => 9000, 'network_overage_price_unit_bytes' => 1073741824,
            'network_usage_direction' => 'egress', 'network_billing_timezone' => 'Asia/Tehran',
        ]);

        return VirtualMachine::query()->create([
            'customer_id' => $customer->id, 'vm_bundle_id' => $bundle->id, 'name' => 'metered-vm',
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'status' => VirtualMachine::STATUS_RUNNING,
            'provisioning_status' => VirtualMachine::PROVISION_READY,
        ])->load(['customer', 'project.owner', 'bundle']);
    }

    /** @return array<string, mixed> */
    private function payload(VirtualMachine $vm, int $bytes, int $revision = 1): array
    {
        return [
            'bucket_id' => 'bucket-1', 'revision' => $revision, 'status' => 'final',
            'vm_uuid' => $vm->uuid, 'assignment_id' => 'assignment-1',
            'interval_start' => '2026-08-15T08:00:00Z', 'interval_end' => '2026-08-15T09:00:00Z',
            'ingress_bytes' => 0, 'egress_bytes' => $bytes, 'completeness' => 'complete',
            'calculation_version' => 'test-1', 'finalized_at' => '2026-08-15T09:05:00Z',
            'updated_at' => '2026-08-15T09:05:00Z',
        ];
    }
}
