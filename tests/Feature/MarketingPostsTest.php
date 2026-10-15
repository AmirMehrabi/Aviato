<?php

namespace Tests\Feature;

use App\Services\MarketingPosts;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class MarketingPostsTest extends TestCase
{
    private string $postsPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->postsPath = sys_get_temp_dir().'/aviato-posts-'.bin2hex(random_bytes(8));
        File::makeDirectory($this->postsPath);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->postsPath);
        parent::tearDown();
    }

    public function test_latest_posts_are_limited_and_sorted_by_date(): void
    {
        $this->writePost('old', '2026-01-01');
        $this->writePost('second', '2026-03-01');
        $this->writePost('latest', '2026-04-01');
        $this->writePost('third', '2026-02-01');
        file_put_contents($this->postsPath.'/invalid.md', 'No frontmatter');

        $posts = (new MarketingPosts($this->postsPath))->latest();

        $this->assertSame(['latest', 'second', 'third'], array_column($posts, 'slug'));
    }

    public function test_cached_posts_refresh_after_additions_edits_and_deletions(): void
    {
        $service = new MarketingPosts($this->postsPath);
        $this->assertSame([], $service->latest());

        $this->writePost('first', '2026-01-01');
        $this->assertSame(['first'], array_column($service->latest(), 'slug'));

        $this->writePost('second', '2026-02-01');
        $this->assertSame(['second', 'first'], array_column($service->latest(), 'slug'));

        $this->writePost('first', '2026-03-01');
        touch($this->postsPath.'/first.md', time() + 2);
        clearstatcache();
        $this->assertSame(['first', 'second'], array_column($service->latest(), 'slug'));

        unlink($this->postsPath.'/first.md');
        $this->assertSame(['second'], array_column($service->latest(), 'slug'));
    }

    private function writePost(string $slug, string $date): void
    {
        file_put_contents($this->postsPath.'/'.$slug.'.md', "---\ntitle: {$slug}\nslug: {$slug}\ndate: {$date}\n---\nContent\n");
    }
}
