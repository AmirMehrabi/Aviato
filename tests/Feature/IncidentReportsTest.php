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

        $this->post('https://admin.localhost/incidents/'.$incident->id.'/timeline', [
            'occurred_at' => '2026-07-25T09:15',
            'title' => 'Investigation started',
            'description' => 'The team began investigating.',
        ])->assertRedirect();

        $this->assertDatabaseHas('incident_timeline_events', ['incident_id' => $incident->id, 'title' => 'Investigation started']);
    }
}
