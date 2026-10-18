<?php

namespace Tests\Feature;

use App\Models\Incident;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_status_renders_ninety_days_and_three_categories(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Tehran'));

        $response = $this->get('/status')->assertOk()
            ->assertDontSee('داده‌های نمونه')
            ->assertSee('ماشین‌های مجازی')->assertSee('شبکه و ارتباطات')->assertSee('پنل کاربری و API')
            ->assertDontSee('فضای ذخیره‌سازی')
            ->assertSee('همه سرویس‌ها فعال هستند')
            ->assertSee('۹۰ روز اخیر')->assertSee('تیر 1405')->assertSee('مرداد 1405')->assertSee('شهریور 1405')->assertSee('مهر 1405')
            ->assertSee('data-tooltip-detail="بدون رخداد؛', false)
            ->assertSee('در این بازه هیچ رخدادی گزارش نشده است.')
            ->assertSee('بازگشت به صفحه اصلی');

        $months = $response->viewData('months');
        $days = collect($months)->flatMap(fn ($month) => $month['days']);
        $this->assertCount(4, $months);
        $this->assertSame('2026-07-21', $days->first()['date']);
        $this->assertSame('2026-10-18', $days->last()['date']);
        $this->assertCount(90, $days);
    }

    public function test_ninety_day_window_crosses_the_jalali_new_year(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-03-25 12:00:00', 'Asia/Tehran'));

        $response = $this->get('/status')->assertOk();
        $months = $response->viewData('months');
        $this->assertSame(['1404-10', '1404-11', '1404-12', '1405-01'], array_keys($months));
        $days = collect($months)->flatMap(fn ($month) => $month['days']);
        $this->assertCount(90, $days);
        $this->assertSame('2025-12-26', $days->first()['date']);
        $this->assertSame('2026-03-25', $days->last()['date']);
    }

    public function test_status_only_exposes_published_started_incidents_and_tracks_active_severity(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Tehran'));
        Incident::factory()->create(['title' => 'Private draft', 'is_published' => false]);
        Incident::factory()->create(['title' => 'Scheduled publication', 'published_at' => now()->addDay()]);
        Incident::factory()->create(['title' => 'Future incident', 'started_at' => now()->addDay(), 'ended_at' => null]);
        Incident::factory()->create(['title' => 'Old resolved incident', 'started_at' => now()->subYear(), 'ended_at' => now()->subYear()->addHour()]);
        $incident = Incident::factory()->create([
            'title' => 'Network outage', 'category' => 'network', 'severity' => 'danger',
            'status' => Incident::STATUS_IDENTIFIED, 'ended_at' => null,
        ]);

        $response = $this->get('/status')->assertOk()->assertSee('Network outage')
            ->assertDontSee('Private draft')->assertDontSee('Scheduled publication')
            ->assertDontSee('Future incident')->assertDontSee('Old resolved incident')
            ->assertSee(route('incidents.show', $incident->slug), false)
            ->assertViewHas('overall', 'danger');
        $this->assertSame('danger', collect($response->viewData('services'))->firstWhere('id', 'network')['severity']);
        $this->assertSame('healthy', collect($response->viewData('services'))->firstWhere('id', 'compute')['severity']);

        $incident->update(['status' => Incident::STATUS_RESOLVED, 'ended_at' => now()]);
        $this->get('/status')->assertOk()->assertViewHas('overall', 'healthy')->assertSee('Network outage');
    }

    public function test_daily_bars_cover_midnight_and_do_not_double_count_overlapping_incidents(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Tehran'));
        foreach ([['2026-10-16 23:00', '2026-10-17 02:00', 'warning'], ['2026-10-17 01:00', '2026-10-17 03:00', 'danger']] as [$start, $end, $severity]) {
            Incident::factory()->create(['category' => 'panel', 'severity' => $severity, 'started_at' => $start, 'ended_at' => $end]);
        }
        $response = $this->get('/status')->assertOk();
        $days = collect($response->viewData('months'))->flatMap(fn ($month) => $month['days'])->keyBy('date');
        $this->assertCount(1, $days['2026-10-16']['incidents']);
        $this->assertCount(2, $days['2026-10-17']['incidents']);
        $this->assertCount(0, $days['2026-10-18']['incidents']);
        $expected = number_format(100 * (1 - 4 / $response->viewData('start')->diffInHours(now())), 2);
        $this->assertSame($expected, collect($response->viewData('services'))->firstWhere('id', 'panel')['uptime']);
    }

    public function test_an_incident_ending_at_midnight_does_not_affect_the_next_day(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Tehran'));
        Incident::factory()->create(['started_at' => '2026-10-16 23:00', 'ended_at' => '2026-10-17 00:00']);
        $response = $this->get('/status')->assertOk();
        $days = collect($response->viewData('months'))->flatMap(fn ($month) => $month['days'])->keyBy('date');
        $this->assertCount(1, $days['2026-10-16']['incidents']);
        $this->assertCount(0, $days['2026-10-17']['incidents']);
    }

    public function test_ongoing_incident_starting_before_the_window_still_affects_status(): void
    {
        $this->travelTo(CarbonImmutable::parse('2026-10-18 12:00:00', 'Asia/Tehran'));
        Incident::factory()->create(['category' => 'compute', 'started_at' => now()->subYear(), 'ended_at' => null, 'status' => Incident::STATUS_MONITORING]);
        $response = $this->get('/status')->assertOk()->assertViewHas('overall', 'warning');
        $this->assertSame('0.00', collect($response->viewData('services'))->firstWhere('id', 'compute')['uptime']);
    }
}
