<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\NetworkUsageRating;
use App\Models\UsageAccrual;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\NetworkUsageIngestionService;
use App\Services\NetworkUsageRatingService;
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
