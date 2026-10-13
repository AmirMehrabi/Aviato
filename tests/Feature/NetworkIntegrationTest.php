<?php

namespace Tests\Feature;

use App\Jobs\RunNetworkIntegration;
use App\Models\Customer;
use App\Models\NetworkIngestionCheckpoint;
use App\Models\NetworkIntegrationRun;
use App\Models\UsageAccrual;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\NetworkUsageIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class NetworkIntegrationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['services.ipdr.url' => 'https://ipdr.example', 'services.ipdr.token' => 'read-token', 'services.ipdr.integration_token' => 'shared-secret']);
        Http::preventStrayRequests();
    }

    public function test_status_page_does_not_call_ipdr_and_explains_waiting_for_data(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $this->get('https://admin.localhost/billing/network/ipdr')->assertOk()
            ->assertSee('همگام‌سازی اکنون')->assertSee('در انتظار اولین مصرف نهایی')->assertSee('نبود داده به معنی مصرف صفر نیست');
        Http::assertNothingSent();
    }

    public function test_sync_is_queued_once_and_backfill_dates_are_converted_from_tehran(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(), 'admin');
        $url = 'https://admin.localhost/billing/network/ipdr/sync';
        $this->post($url, ['from' => '2026-10-01T03:30', 'to' => '2026-10-02T03:30'])->assertRedirect();
        $run = NetworkIntegrationRun::first();
        $this->assertSame('2026-10-01T00:00:00+00:00', $run->payload['from']);
        $this->assertSame('2026-10-02T00:00:00+00:00', $run->payload['to']);
        $this->post($url)->assertRedirect();
        $this->assertDatabaseCount('network_integration_runs', 1);
        Queue::assertPushed(RunNetworkIntegration::class, 1);
        $this->get('https://admin.localhost/billing/network/ipdr')->assertOk()->assertSee('در صف');
    }

    public function test_backfill_requires_both_dates(): void
    {
        Queue::fake();
        $this->actingAs(User::factory()->create(), 'admin');
        $this->post('https://admin.localhost/billing/network/ipdr/sync', ['from' => '2026-10-01T00:00'])->assertSessionHasErrors('to');
        Queue::assertNothingPushed();
    }

    public function test_delivery_is_authenticated_durable_and_idempotent_with_poison_records_isolated(): void
    {
        Queue::fake();
        $items = [$this->payload(), ['bucket_id' => 'invalid']];
        $batch = hash('sha256', json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $url = '/api/internal/v1/metering/usage';
        $this->postJson($url, ['batch_id' => $batch, 'items' => $items])->assertUnauthorized();
        $this->withToken('shared-secret')->postJson($url, ['batch_id' => $batch, 'items' => $items])->assertStatus(202)->assertJsonPath('accepted', true);
        $this->withToken('shared-secret')->postJson($url, ['batch_id' => $batch, 'items' => $items])->assertStatus(202);
        $this->assertDatabaseCount('network_integration_runs', 1);
        Queue::assertPushed(RunNetworkIntegration::class, 1);
        app()->call([new RunNetworkIntegration(NetworkIntegrationRun::first()->id), 'handle']);
        $run = NetworkIntegrationRun::first();
        $this->assertSame('completed', $run->status);
        $this->assertSame(2, $run->stats['received']);
        $this->assertCount(1, $run->errors);
        $this->assertDatabaseHas('network_usage_buckets', ['bucket_id' => 'bucket-delivery', 'processing_status' => 'quarantined']);
        $this->assertDatabaseCount('network_usage_bucket_revisions', 1);
        $this->withToken('shared-secret')->postJson($url, ['batch_id' => str_repeat('a', 64), 'items' => $items])->assertUnprocessable();
    }

    public function test_backfill_uses_dates_only_on_first_page_and_preserves_incremental_checkpoint(): void
    {
        NetworkIngestionCheckpoint::create(['source' => 'ipdr', 'cursor' => 'incremental']);
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'cursor=')) {
                $this->assertStringNotContainsString('from=', $request->url());
                $this->assertStringNotContainsString('to=', $request->url());

                return Http::response(['items' => [], 'next_cursor' => 'end', 'has_more' => false]);
            }
            $this->assertStringContainsString('from=', $request->url());

            return Http::response(['items' => [$this->payload()], 'next_cursor' => 'backfill-page-2', 'has_more' => true]);
        });
        $stats = app(NetworkUsageIngestionService::class)->sync('2026-10-01T00:00:00Z', '2026-10-02T00:00:00Z');
        $this->assertSame(2, $stats['pages']);
        $this->assertSame('incremental', NetworkIngestionCheckpoint::first()->cursor);
        Http::assertSentCount(2);
    }

    public function test_expired_cursor_replays_retained_stream_without_duplicate_revisions(): void
    {
        NetworkIngestionCheckpoint::create(['source' => 'ipdr', 'cursor' => 'expired']);
        app(NetworkUsageIngestionService::class)->ingest('ipdr', $this->payload());
        Http::fake(fn ($request) => str_contains($request->url(), 'cursor=expired')
            ? Http::response(['error' => ['code' => 'INVALID_CURSOR']], 400)
            : Http::response(['items' => [$this->payload()], 'next_cursor' => 'fresh', 'has_more' => false]));
        $stats = app(NetworkUsageIngestionService::class)->sync();
        $this->assertTrue($stats['recovered_cursor']);
        $this->assertSame('fresh', NetworkIngestionCheckpoint::first()->cursor);
        $this->assertDatabaseCount('network_usage_bucket_revisions', 1);
    }

    public function test_sync_job_reports_completion_and_health(): void
    {
        Http::fake([
            'ipdr.example/api/v1/integration/status' => Http::response(['inventory' => ['cursor' => '0'], 'collector' => [], 'delivery' => []]),
            'ipdr.example/api/v1/usage/buckets*' => Http::response(['items' => [], 'next_cursor' => 'done', 'has_more' => false]),
        ]);
        $run = NetworkIntegrationRun::create(['kind' => 'sync']);
        app()->call([new RunNetworkIntegration($run->id), 'handle']);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(0, $run->fresh()->stats['received']);
        $this->assertSame('0', $run->fresh()->health['inventory']['cursor']);
    }

    public function test_direct_delivery_then_polling_cannot_charge_the_same_usage_twice(): void
    {
        Queue::fake();
        $customer = Customer::factory()->create();
        $bundle = VmBundle::create(['name' => 'Metered', 'slug' => 'delivery-metered', 'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'monthly_price' => 0,
            'network_accounting_enabled' => true, 'network_included_bytes_monthly' => 0, 'network_overage_price' => 9000, 'network_overage_price_unit_bytes' => 1073741824, 'network_usage_direction' => 'egress', 'network_billing_timezone' => 'Asia/Tehran']);
        $vm = VirtualMachine::create(['customer_id' => $customer->id, 'vm_bundle_id' => $bundle->id, 'name' => 'delivered-vm', 'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'status' => 'running', 'provisioning_status' => 'ready']);
        $item = array_replace($this->payload(), ['vm_uuid' => $vm->uuid, 'egress_bytes' => 1073741824]);
        $items = [$item];
        $batch = hash('sha256', json_encode($items, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
        $this->withToken('shared-secret')->postJson('/api/internal/v1/metering/usage', ['batch_id' => $batch, 'items' => $items])->assertStatus(202);
        app()->call([new RunNetworkIntegration(NetworkIntegrationRun::first()->id), 'handle']);
        app(NetworkUsageIngestionService::class)->retryUnrated();
        // A pull response may serialize object keys in a different order.
        Http::fake(['*' => Http::response(['items' => [array_reverse($item, true)], 'next_cursor' => 'done', 'has_more' => false])]);
        app(NetworkUsageIngestionService::class)->sync();
        app(NetworkUsageIngestionService::class)->retryUnrated();
        $this->assertDatabaseCount('network_usage_ratings', 1);
        $this->assertDatabaseHas('usage_accruals', ['category' => UsageAccrual::CATEGORY_NETWORK, 'amount' => 9000]);
    }

    public function test_backfill_continues_in_a_durable_job_with_its_cursor(): void
    {
        Queue::fake();
        Http::fake(function ($request) {
            if (str_contains($request->url(), 'integration/status')) {
                return Http::response([]);
            }

            return Http::response(['items' => [], 'next_cursor' => 'next-page', 'has_more' => true]);
        });
        $run = NetworkIntegrationRun::create(['kind' => 'backfill', 'payload' => ['from' => '2026-10-01T00:00:00Z', 'to' => '2026-10-02T00:00:00Z']]);
        app()->call([new RunNetworkIntegration($run->id), 'handle']);
        $next = NetworkIntegrationRun::latest('id')->first();
        $this->assertNotSame($run->id, $next->id);
        $this->assertSame('next-page', $next->payload['cursor']);
        $this->assertSame('completed', $run->fresh()->status);
        Queue::assertPushed(RunNetworkIntegration::class, fn ($job) => $job->runId === $next->id);
    }

    private function payload(): array
    {
        return ['bucket_id' => 'bucket-delivery', 'revision' => 1, 'status' => 'final',
            'vm_uuid' => '882c29c8-6aac-4d80-90fc-959fd600ec96', 'assignment_id' => 'assignment-delivery',
            'interval_start' => '2026-10-01T00:00:00Z', 'interval_end' => '2026-10-01T01:00:00Z',
            'ingress_bytes' => 10, 'egress_bytes' => 20, 'completeness' => 'complete',
            'finalized_at' => '2026-10-01T01:10:00Z', 'updated_at' => '2026-10-01T01:10:00Z'];
    }
}
