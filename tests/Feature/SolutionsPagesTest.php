<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SolutionsPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_discover_every_service_from_the_overview_and_sitemap(): void
    {
        $overview = $this->get('/solutions')->assertOk();
        $sitemap = $this->get('/sitemap.xml')->assertOk();

        foreach (config('solutions') as $service) {
            $overview->assertSee($service['label']);
            $overview->assertSee(route($service['route']), false);
            $sitemap->assertSee(route($service['route']), false);
        }
    }

    public function test_service_pages_are_public_and_link_to_the_correct_next_step(): void
    {
        foreach (['cloud', 'domains', 'dns'] as $key) {
            $service = config('solutions.'.$key);

            $this->get(route($service['route']))
                ->assertOk()
                ->assertSee($service['title'])
                ->assertSee(route($service['action_route']), false)
                ->assertSee($service['note']);
        }
    }
}
