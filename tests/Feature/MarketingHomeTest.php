<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Js;
use Tests\TestCase;

class MarketingHomeTest extends TestCase
{
    use RefreshDatabase;

    public function test_homepage_contains_the_approved_services_and_public_domain_search(): void
    {
        $response = $this->get('/')->assertOk()->assertViewIs('home');
        $response->assertDontSee('پیش‌نمایش پیشنهادی صفحه اصلی');
        $this->assertFalse($response->headers->has('X-Robots-Tag'));
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
        $response->assertSee('از اولین راه‌اندازی تا اوج رشد، زیرساختی پایدار برای سرویس‌های همیشه‌روشن.');
        $response->assertSee('homepage-domain');
        $response->assertSee(Js::from(route('marketing.domains.check', [], false))->toHtml(), false);
        foreach (['pricing', 'customer.domains.index', 'customer.cloud-dns.index', 'solutions.colocation'] as $route) {
            $response->assertSee(route($route), false);
        }
    }

    public function test_customer_selection_has_three_distinct_customers_in_their_fixed_order(): void
    {
        $order = ['حسابرو', 'کارخانه نوآوری کرمان', 'مبیت', 'آوینوکس', 'پی‌بای'];
        $response = $this->get('/')->assertOk();
        preg_match_all('/<img[^>]*src="[^"]*\/assets\/images\/customers\/[^>]*alt="([^"]+)"/', $response->getContent(), $matches);
        $names = $matches[1];
        $this->assertCount(3, $names);
        $this->assertCount(3, array_unique($names));
        $positions = array_map(fn ($name) => array_search($name, $order, true), $names);
        $sorted = $positions;
        sort($sorted);
        $this->assertSame($sorted, $positions);
    }

    public function test_old_preview_redirects_and_is_not_advertised_in_the_sitemap(): void
    {
        $this->get('/preview/home')->assertRedirect('/');
        $this->get('/sitemap.xml')->assertOk()->assertDontSee(route('preview.home'), false);
    }
}
