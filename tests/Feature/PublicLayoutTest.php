<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicLayoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_and_status_share_the_same_header_and_footer_except_the_logo(): void
    {
        $status = $this->get(route('status'))->assertOk();
        $blog = $this->get(route('blog'))->assertOk();
        $status->assertSee('assets/images/aviato_logo_full_black.png', false);
        $blog->assertSee('assets/images/aviato_logo_full_color.webp', false);

        foreach (['header', 'footer'] as $element) {
            preg_match('/<'.$element.' class="site-'.$element.'">.*?<\/'.$element.'>/s', $status->getContent(), $statusMatch);
            preg_match('/<'.$element.' class="site-'.$element.'">.*?<\/'.$element.'>/s', $blog->getContent(), $blogMatch);
            $this->assertNotEmpty($statusMatch);
            $this->assertNotEmpty($blogMatch);
            $this->assertSame(
                str_replace('aviato_logo_full_black.png', 'aviato_logo_full_color.webp', $statusMatch[0]),
                $blogMatch[0],
            );
        }
        foreach ([$status, $blog] as $response) {
            $response->assertSee('class="public-shell"', false)
                ->assertSee('بازگشت به صفحه اصلی')
                ->assertSee('assets/css/public.css', false);
        }
    }

    public function test_both_marketing_footer_variants_link_to_the_status_page(): void
    {
        foreach (['home', 'contact'] as $route) {
            $response = $this->get(route($route))->assertOk();
            preg_match('/<footer\b.*?<\/footer>/s', $response->getContent(), $footer);
            $this->assertNotEmpty($footer);
            $this->assertStringContainsString('href="'.route('status').'"', $footer[0]);
            $this->assertStringContainsString('وضعیت سرویس‌ها', $footer[0]);
        }
    }
}
