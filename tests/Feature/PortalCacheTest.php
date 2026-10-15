<?php

namespace Tests\Feature;

use App\Enums\AdminRole;
use App\Models\Customer;
use App\Models\Payment;
use App\Models\ProjectMember;
use App\Models\User;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\AdminDashboardFinance;
use App\Services\PortalCache;
use App\Services\ProjectAccessService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Tests\TestCase;

class PortalCacheTest extends TestCase
{
    use RefreshDatabase;

    public function test_warm_finance_snapshot_reduces_queries_and_payment_changes_invalidate_it(): void
    {
        $customer = Customer::factory()->create();
        $payment = Payment::create([
            'customer_id' => $customer->id, 'provider' => 'dummy',
            'wallet_id' => $customer->wallet->id, 'type' => Payment::TYPE_TOP_UP,
            'status' => Payment::STATUS_PENDING, 'amount' => 1000, 'currency' => 'IRR',
        ]);
        $finance = app(AdminDashboardFinance::class);
        DB::enableQueryLog();
        $cold = $finance->snapshot(7);
        $coldQueries = count(DB::getQueryLog());
        DB::flushQueryLog();
        $warm = $finance->snapshot(7);
        $warmQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertSame(1, $cold['pending_attempts']);
        $this->assertSame($cold['pending_attempts'], $warm['pending_attempts']);
        $this->assertLessThan($coldQueries, $warmQueries);
        $this->assertLessThanOrEqual(2, $warmQueries);

        $payment->update(['status' => Payment::STATUS_FAILED]);
        $this->assertSame(0, $finance->snapshot(7)['pending_attempts']);
        $this->assertSame(1, $finance->snapshot(7)['failed_attempts']);
    }

    public function test_cache_expiry_and_scope_separation(): void
    {
        $cache = app(PortalCache::class);
        $this->assertSame(1, $cache->remember('admin', 'same', 15, fn () => 1));
        $this->assertSame(2, $cache->remember('customer', 'same', 15, fn () => 2));
        $this->assertSame(1, $cache->remember('admin', 'same', 15, fn () => 3));
        $this->travel(16)->seconds();
        $this->assertSame(3, $cache->remember('admin', 'same', 15, fn () => 3));
    }

    public function test_page_data_is_recomputed_when_optional_cache_is_unavailable(): void
    {
        Cache::shouldReceive('store')->once()->andThrow(new RuntimeException('Cache unavailable'));
        $this->assertSame(['count' => 42], app(PortalCache::class)->remember('admin', 'example', 15, fn () => ['count' => 42]));
    }

    public function test_computation_errors_are_not_hidden_or_retried(): void
    {
        $calls = 0;
        try {
            app(PortalCache::class)->remember('admin', 'broken', 15, function () use (&$calls): never {
                $calls++;
                throw new RuntimeException('Query failed');
            });
            $this->fail('Expected computation exception');
        } catch (RuntimeException $exception) {
            $this->assertSame('Query failed', $exception->getMessage());
            $this->assertSame(1, $calls);
        }
        $this->assertSame(4, app(PortalCache::class)->remember('admin', 'broken', 15, fn () => 4));
    }

    public function test_warm_dashboard_does_not_expose_finance_after_permission_change(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin')->get('https://admin.localhost/dashboard')
            ->assertOk()->assertViewHas('finance', fn ($value) => is_array($value));
        $admin->update(['role' => AdminRole::Custom, 'permissions' => ['dashboard.view']]);
        $this->get('https://admin.localhost/dashboard')->assertOk()->assertViewHas('finance', null);
    }

    public function test_customer_dashboard_cache_is_isolated_and_price_updates_invalidate_it(): void
    {
        $first = Customer::factory()->create();
        $second = Customer::factory()->create();
        $bundle = VmBundle::create(['name' => 'Cached plan', 'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'monthly_price' => 730000]);
        VirtualMachine::create([
            'customer_id' => $first->id, 'vm_bundle_id' => $bundle->id, 'name' => 'cache-test',
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10,
            'status' => VirtualMachine::STATUS_RUNNING, 'provisioning_status' => VirtualMachine::PROVISION_READY,
        ]);

        $this->actingAs($first, 'customer')->get('https://cp.localhost/dashboard')
            ->assertOk()->assertViewHas('summary', fn ($summary) => $summary['monthly_spend'] === 730000);
        $this->get('https://cp.localhost/dashboard')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['monthly_spend'] === 730000);
        $bundle->update(['monthly_price' => 1460000]);
        $this->get('https://cp.localhost/dashboard')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['monthly_spend'] === 1460000);

        $this->actingAs($second, 'customer')
            ->withSession([ProjectAccessService::SESSION_KEY => $second->ensureDefaultProject()->id])
            ->get('https://cp.localhost/dashboard')->assertOk()
            ->assertViewHas('summary', fn ($summary) => $summary['monthly_spend'] === 0);

        $project = $first->ensureDefaultProject();
        $project->members()->create(['customer_id' => $second->id, 'role' => ProjectMember::ROLE_MEMBER]);
        $this->withSession([ProjectAccessService::SESSION_KEY => $project->id])
            ->get('https://cp.localhost/dashboard')->assertOk()
            ->assertViewHas('canViewBilling', false)
            ->assertViewHas('summary', fn ($summary) => $summary['monthly_spend'] === 0);
    }
}
