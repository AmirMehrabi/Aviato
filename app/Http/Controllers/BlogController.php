<?php

namespace App\Http\Controllers;

use Illuminate\Support\Str;
use League\CommonMark\Environment\Environment;
use League\CommonMark\Extension\CommonMark\CommonMarkCoreExtension;
use League\CommonMark\Extension\Table\TableExtension;
use League\CommonMark\MarkdownConverter;

class BlogController extends Controller
{
    private string $postsPath;

    public function __construct()
    {
        $this->postsPath = resource_path('blog/posts');
    }

    public function index()
    {
        $posts = $this->getPosts();
        $featuredPost = collect($posts)->firstWhere('featured', true) ?? $posts[0] ?? null;
        $regularPosts = collect($posts)
            ->reject(fn (array $post): bool => $featuredPost && $post['slug'] === $featuredPost['slug'])
            ->values()
            ->all();

        return view('blog.index', [
            'posts' => $posts,
            'featuredPost' => $featuredPost,
            'regularPosts' => $regularPosts,
            'categories' => collect($posts)->pluck('category')->filter()->unique()->values()->all(),
            'activePage' => 'blog',
        ]);
    }

    public function show(string $slug)
    {
        $posts = $this->getPosts();
        $post = collect($posts)->firstWhere('slug', $slug);

        if (! $post) {
            abort(404);
        }

        $relatedPosts = collect($posts)
            ->reject(fn (array $item): bool => $item['slug'] === $post['slug'])
            ->sortByDesc(fn (array $item): int => $item['category'] === $post['category'] ? 1 : 0)
            ->take(3)
            ->values()
            ->all();

        return view('blog.show', [
            'post' => $post,
            'relatedPosts' => $relatedPosts,
            'activePage' => 'blog',
        ]);
    }

    private function getPosts(): array
    {
        $files = glob($this->postsPath.'/*.md');

        $posts = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);
            $post = $this->parsePost($content);

            if ($post) {
                $posts[] = $post;
            }
        }

        usort($posts, fn ($a, $b) => $b['date'] <=> $a['date']);

        return $posts;
    }

    private function parsePost(string $content): ?array
    {
        if (preg_match('/^---\n(.+?)\n---\n(.+)$/s', $content, $matches)) {
            $meta = $this->parseYaml($matches[1]);
            $markdown = $matches[2];

            $env = new Environment([]);
            $env->addExtension(new CommonMarkCoreExtension);
            $env->addExtension(new TableExtension);
            $converter = new MarkdownConverter($env);

            $html = $converter->convertToHtml($markdown);
            $toc = [];
            $headingIndex = 0;

            $html = preg_replace_callback('/<h([23])>(.*?)<\/h\1>/si', function (array $matches) use (&$toc, &$headingIndex): string {
                $headingIndex++;
                $headingText = trim(strip_tags($matches[2]));
                $id = Str::slug($headingText, '-') ?: 'section-'.$headingIndex;
                $id = $id.'-'.$headingIndex;
                $toc[] = [
                    'id' => $id,
                    'label' => $headingText,
                    'level' => (int) $matches[1],
                ];

                return '<h'.$matches[1].' id="'.$id.'">'.$matches[2].'</h'.$matches[1].'>';
            }, $html) ?? $html;

            return [
                'title' => $meta['title'] ?? '',
                'slug' => $meta['slug'] ?? '',
                'author' => $meta['author'] ?? 'آویاتو',
                'author_avatar' => $meta['author_avatar'] ?? 'team',
                'date' => $meta['date'] ?? '',
                'date_display' => $meta['date_display'] ?? '',
                'updated_date' => $meta['updated_date'] ?? '',
                'category' => $meta['category'] ?? '',
                'reading_time' => $meta['reading_time'] ?? '',
                'excerpt' => $meta['excerpt'] ?? '',
                'cover_image' => $meta['cover_image'] ?? '',
                'featured' => filter_var($meta['featured'] ?? false, FILTER_VALIDATE_BOOLEAN),
                'tags' => array_values(array_filter(array_map('trim', explode(',', $meta['tags'] ?? '')))),
                'toc' => $toc,
                'content' => (string) $html,
            ];
        }

        return null;
    }

    private function parseYaml(string $yaml): array
    {
        $result = [];
        $lines = explode("\n", $yaml);

        foreach ($lines as $line) {
            if (preg_match('/^(\w+):\s*"?(.+?)"?$/', trim($line), $matches)) {
                $result[$matches[1]] = $matches[2];
            }
        }

        return $result;
    }
}
