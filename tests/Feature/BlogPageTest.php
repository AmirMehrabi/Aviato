<?php

namespace Tests\Feature;

use Tests\TestCase;

class BlogPageTest extends TestCase
{
    public function test_guest_can_view_the_blog_index_with_article_discovery_controls(): void
    {
        $response = $this->get(route('blog'));

        $response->assertOk();
        $response->assertSee('بلاگ آویاتو');
        $response->assertSee('همه');
        $response->assertSee('محصول');
        $response->assertSee('مطالعه مقاله');
    }

    public function test_guest_can_view_an_article_with_navigation_and_related_content(): void
    {
        $response = $this->get(route('blog.show', 'why-we-publish-aviato-changelog-publicly'));

        $response->assertOk();
        $response->assertSee('چرا ما لیست تغییرات آویاتو را عمومی منتشر می‌کنیم؟');
        $response->assertSee('در این مقاله');
        $response->assertSee('کپی لینک مقاله');
        $response->assertSee('مطالب مرتبط');
        $response->assertSee('دیدن پلن‌ها');
    }

    public function test_unknown_article_slug_returns_not_found(): void
    {
        $this->get(route('blog.show', 'does-not-exist'))->assertNotFound();
    }
}
