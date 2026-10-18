<?php

namespace Tests\Feature;

use App\Models\Incident;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IncidentReportsTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'portals.admin.domain' => 'admin.localhost',
            'portals.customer.domain' => 'cp.localhost',
        ]);
    }

    public function test_public_pages_only_show_published_incidents_and_render_the_report_sections(): void
    {
        Incident::factory()->create(['title' => 'Draft report', 'slug' => 'draft-report', 'is_published' => false]);
        $published = Incident::factory()->create(['slug' => 'published-report', 'is_published' => true]);
        $published->timelineEvents()->create(['occurred_at' => $published->started_at, 'title' => 'Investigation started', 'description' => 'We began investigating.']);

        $this->get(route('incidents.index'))->assertOk()->assertSee('published-report', false)->assertDontSee('Draft report');
        $this->get(route('incidents.show', $published->slug))->assertOk()
            ->assertSee('خلاصه')->assertSee('گاهشمار')->assertSee('علت ریشه‌ای')
            ->assertSee('تأثیر بر مشتریان')->assertSee('رفع مشکل')->assertSee('وضعیت نهایی');
        $this->get(route('incidents.show', 'draft-report'))->assertNotFound();
    }

    public function test_admin_can_create_an_incident_and_manage_its_timeline(): void
    {
        $admin = User::factory()->create();
        $this->actingAs($admin, 'admin');

        $response = $this->post('https://admin.localhost/incidents', [
            'title' => 'Test network incident',
            'status' => Incident::STATUS_IDENTIFIED,
            'affected_service' => 'Network',
            'category' => 'network',
            'severity' => 'danger',
            'impact_summary' => 'Short impact summary.',
            'summary' => 'Summary text.',
            'started_at' => '2026-07-25T09:00',
            'is_published' => 1,
        ]);
        $response->assertSessionHasNoErrors();

        $response->assertStatus(302);
        $incident = Incident::query()->firstOrFail();
        $response->assertRedirect('https://admin.localhost/incidents/'.$incident->id.'/edit');
        $this->assertTrue($incident->is_published);
        $this->assertSame('network', $incident->category);
        $this->assertSame('danger', $incident->severity);

        $this->post('https://admin.localhost/incidents/'.$incident->id.'/timeline', [
            'occurred_at' => '2026-07-25T09:15',
            'title' => 'Investigation started',
            'description' => 'The team began investigating.',
        ])->assertRedirect();

        $this->assertDatabaseHas('incident_timeline_events', ['incident_id' => $incident->id, 'title' => 'Investigation started']);
    }

    public function test_admin_forms_display_categories_and_severity_and_reject_invalid_values(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $this->get('https://admin.localhost/incidents/create')->assertOk()
            ->assertSee('ماشین‌های مجازی')->assertSee('شبکه و ارتباطات')->assertSee('پنل کاربری و API')
            ->assertSee('name="category"', false)->assertSee('name="severity"', false);

        $payload = Incident::factory()->make()->only([
            'title', 'status', 'category', 'severity', 'affected_service', 'impact_summary', 'summary', 'started_at', 'ended_at',
        ]);
        $this->post('https://admin.localhost/incidents', [...$payload, 'category' => 'unknown', 'severity' => 'unknown'])
            ->assertSessionHasErrors(['category', 'severity']);
        $this->post('https://admin.localhost/incidents', [...$payload, 'category' => null, 'severity' => null])
            ->assertSessionHasErrors(['category', 'severity']);
    }

    public function test_resolving_an_incident_sets_its_end_and_reopening_updates_live_status(): void
    {
        $this->actingAs(User::factory()->create(), 'admin');
        $incident = Incident::factory()->create(['category' => 'network', 'severity' => 'danger', 'status' => Incident::STATUS_IDENTIFIED, 'ended_at' => null]);
        $payload = $incident->only(['title', 'slug', 'category', 'severity', 'affected_service', 'impact_summary', 'summary']);
        $payload['started_at'] = $incident->started_at->format('Y-m-d H:i:s');
        $payload['is_published'] = true;

        $this->put('https://admin.localhost/incidents/'.$incident->id, [...$payload, 'status' => Incident::STATUS_RESOLVED])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull($incident->fresh()->ended_at);
        $this->get('/status')->assertOk()->assertViewHas('overall', 'healthy');

        $this->put('https://admin.localhost/incidents/'.$incident->id, [...$payload, 'status' => Incident::STATUS_MONITORING, 'severity' => 'warning', 'category' => 'panel'])
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNull($incident->fresh()->ended_at);
        $response = $this->get('/status')->assertOk()->assertViewHas('overall', 'warning');
        $this->assertSame('warning', collect($response->viewData('services'))->firstWhere('id', 'panel')['severity']);
        $this->assertSame('healthy', collect($response->viewData('services'))->firstWhere('id', 'network')['severity']);
        $this->get('https://admin.localhost/incidents')->assertOk()->assertSee('پنل کاربری و API');
    }
}
