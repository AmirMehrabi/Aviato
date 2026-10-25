<?php

namespace Tests\Feature;

use App\Models\Customer;
use App\Models\UsageAccrual;
use App\Models\UsageSettlement;
use App\Models\VirtualMachine;
use App\Models\VmBundle;
use App\Services\InvoiceService;
use App\Services\NetworkUsageIngestionService;
use App\Services\NetworkUsageRatingService;
use App\Services\UsageBillingService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonthlyInvoiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_previous_jalali_month_handles_different_lengths_and_year_rollover(): void
    {
        foreach ([
            ['2026-10-23', '2026-09-23', '2026-10-22', 30],
            ['2026-07-23', '2026-06-22', '2026-07-22', 31],
            ['2026-03-21', '2026-02-20', '2026-03-20', 29],
            ['2025-03-21', '2025-02-19', '2025-03-20', 30],
        ] as [$now, $start, $end, $days]) {
            [$from, $to] = app(InvoiceService::class)->previousMonthPeriod(CarbonImmutable::parse($now, 'Asia/Tehran'));
            $this->assertSame($start.' 00:00:00', $from->format('Y-m-d H:i:s'));
            $this->assertSame($end.' 23:59:59', $to->format('Y-m-d H:i:s'));
            $this->assertEquals($days, $from->diffInDays($to->addMicrosecond()));
        }
    }

    public function test_invoice_schedule_runs_on_first_jalali_day_in_tehran(): void
    {
        $event = collect(app(Schedule::class)->events())->first(
            fn ($event) => str_contains($event->command ?? '', 'billing:generate-monthly-invoices'),
        );
        $this->assertNotNull($event);

        foreach ([
            ['2026-10-22T20:45:00Z', true], // Aban 1, 00:15 Tehran.
            ['2026-10-22T20:44:00Z', false],
            ['2026-10-23T20:45:00Z', false],
            ['2026-09-30T20:45:00Z', false], // Gregorian October 1.
        ] as [$instant, $expected]) {
            $this->travelTo(CarbonImmutable::parse($instant));
            $this->assertSame($expected, $event->isDue($this->app) && $event->filtersPass($this->app));
        }
        $this->travelBack();
    }

    public function test_invoice_prorates_compute_and_separates_network_usage_for_each_vm(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-23 00:30:00', 'Asia/Tehran'));
        $customer = Customer::factory()->create();
        $bundle = VmBundle::create([
            'name' => 'Metered', 'slug' => 'invoice-metered', 'cpu_cores' => 1,
            'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
            'monthly_price' => 730000, 'hourly_price' => 1000, 'is_active' => true,
            'network_accounting_enabled' => true, 'network_included_bytes_monthly' => 0,
            'network_overage_price' => 9000, 'network_overage_price_unit_bytes' => 1073741824,
            'network_usage_direction' => 'egress', 'network_billing_timezone' => 'Asia/Tehran',
        ]);
        $vm = $this->vm($customer, $bundle, 'compute-and-network');
        $freeVm = $this->vm($customer, $bundle, 'free-network');
        $freeVm->update(['network_included_bytes_monthly_override' => 1099511627776]);
        $otherVm = $this->vm(Customer::factory()->create(), $bundle, 'another-customer');

        // One interval crosses the Jalali month boundary; only 1.5 hours belong to Mehr.
        $vm->update(['last_billed_at' => CarbonImmutable::parse('2026-10-22 22:30:00', 'Asia/Tehran')]);
        app(UsageBillingService::class)->accrueVm($vm);
        $this->rateBucket($vm, 'charged-1', '2026-10-22T19:00:00Z');
        $this->rateBucket($vm, 'charged-2', '2026-10-22T19:30:00Z');
        $this->rateBucket($freeVm, 'free', '2026-10-22T19:00:00Z');
        $this->rateBucket($otherVm, 'other', '2026-10-22T19:00:00Z');
        $this->rateBucket($vm, 'next-month', '2026-10-22T20:30:00Z');
        app(UsageBillingService::class)->settleDate('2026-10-22');
        $balance = $customer->wallet()->firstOrFail()->balance;
        $originalSettlement = UsageSettlement::query()->where('customer_id', $customer->id)->firstOrFail();
        // This bucket arrives after the daily settlement and must still reach the invoice.
        $this->rateBucket($vm, 'late-finalized', '2026-10-22T20:00:00Z');

        $invoice = app(InvoiceService::class)->generateMonthlyInvoices()->firstWhere('customer_id', $customer->id);
        $this->assertNotNull($invoice);
        $this->assertStringStartsWith('INV-140507-', $invoice->number);
        $this->assertSame(28500, $invoice->total_amount);
        $this->assertSame($originalSettlement->amount, $originalSettlement->fresh()->amount);
        $this->assertSame($originalSettlement->wallet_transaction_id, $originalSettlement->fresh()->wallet_transaction_id);
        $this->assertCount(3, $invoice->items);
        $compute = $invoice->items->firstWhere('label', 'compute-and-network');
        $this->assertEquals(1.5, $compute->quantity);
        $this->assertSame(1500, $compute->subtotal);
        $network = $invoice->items->filter(fn ($item) => $item->meta['category'] === UsageAccrual::CATEGORY_NETWORK);
        $this->assertCount(2, $network);
        $charged = $network->firstWhere('virtual_machine_id', $vm->id);
        $this->assertSame('period', $charged->unit);
        $this->assertEquals(27000, $charged->unit_price);
        $this->assertSame(3221225472, $charged->meta['snapshot']['egress_bytes']);
        $this->assertSame(0, $network->firstWhere('virtual_machine_id', $freeVm->id)->subtotal);
        $this->assertSame($balance - 9000, $customer->wallet()->firstOrFail()->balance);
        $this->assertDatabaseHas('usage_accruals', ['virtual_machine_id' => $vm->id, 'amount' => 500]);

        app(InvoiceService::class)->generateMonthlyInvoices();
        $this->assertSame($balance - 9000, $customer->wallet()->firstOrFail()->balance);
        $this->assertSame(1, $customer->invoices()->count());
        $this->actingAs($customer, 'customer')->get('http://cp.localhost/invoices/'.$invoice->id)
            ->assertOk()->assertSee('compute-and-network - Network')->assertSee('free-network - Network')->assertSee('GiB');
        $this->travelBack();
    }

    private function vm(Customer $customer, VmBundle $bundle, string $name): VirtualMachine
    {
        return VirtualMachine::create([
            'customer_id' => $customer->id, 'vm_bundle_id' => $bundle->id, 'name' => $name,
            'cpu_cores' => 1, 'ram_gb' => 1, 'disk_gb' => 10, 'ip_count' => 1,
            'status' => VirtualMachine::STATUS_RUNNING, 'provisioning_status' => VirtualMachine::PROVISION_READY,
            'tax_exempt' => true,
        ]);
    }

    private function rateBucket(VirtualMachine $vm, string $id, string $start): void
    {
        $end = CarbonImmutable::parse($start)->addMinutes(30)->toIso8601String();
        $bucket = app(NetworkUsageIngestionService::class)->ingest('invoice-test', [
            'bucket_id' => $id, 'revision' => 1, 'status' => 'final', 'vm_uuid' => $vm->uuid,
            'assignment_id' => 'assignment-'.$vm->id, 'interval_start' => $start, 'interval_end' => $end,
            'ingress_bytes' => 0, 'egress_bytes' => 1073741824, 'completeness' => 'complete',
            'calculation_version' => 'test-1', 'finalized_at' => $end, 'updated_at' => $end,
        ]);
        $this->assertTrue(app(NetworkUsageRatingService::class)->rate($bucket));
    }
}
