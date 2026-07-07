<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MarketingSitemapTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_the_sitemap_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee(route('home'), false);
        $response->assertSee(route('pricing'), false);
        $response->assertSee(route('solutions'), false);
        $response->assertSee(route('blog'), false);
        $response->assertSee(route('changelog'), false);
        $response->assertSee(route('contact'), false);
        $response->assertSee(route('blog.show', 'why-affordable-cloud-infrastructure-is-needed-in-iran'), false);
    }

    public function test_home_page_navigation_contains_all_public_marketing_pages(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee(route('home'), false);
        $response->assertSee(route('pricing'), false);
        $response->assertSee(route('solutions'), false);
        $response->assertSee(route('blog'), false);
        $response->assertSee(route('changelog'), false);
        $response->assertSee(route('contact'), false);
    }
}
