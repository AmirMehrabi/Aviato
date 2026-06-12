<?php

namespace App\Http\Controllers;

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

        return view('blog.index', [
            'posts' => $posts,
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

        return view('blog.show', [
            'post' => $post,
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

            return [
                'title' => $meta['title'] ?? '',
                'slug' => $meta['slug'] ?? '',
                'author' => $meta['author'] ?? 'آویاتو',
                'date' => $meta['date'] ?? '',
                'date_display' => $meta['date_display'] ?? '',
                'category' => $meta['category'] ?? '',
                'reading_time' => $meta['reading_time'] ?? '',
                'excerpt' => $meta['excerpt'] ?? '',
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
