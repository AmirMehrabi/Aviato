<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class MarketingPosts
{
    public function __construct(private ?string $postsPath = null) {}

    public function latest(): array
    {
        $files = glob(($this->postsPath ?? resource_path('blog/posts')).'/*.md') ?: [];
        $revisions = array_map(fn (string $file): array => [$file, filemtime($file), filesize($file)], $files);
        $key = 'marketing.posts.v1.'.hash('sha256', serialize($revisions));

        // Additions, edits and deletions get a new key; obsolete entries expire after ten minutes.
        return Cache::remember($key, 600, function () use ($files): array {
            $posts = [];

            foreach ($files as $file) {
                $content = file_get_contents($file);
                if (preg_match('/^---\n(.+?)\n---\n(.+)$/s', $content, $matches)) {
                    $meta = [];
                    foreach (explode("\n", $matches[1]) as $line) {
                        if (preg_match('/^(\w+):\s*"?(.+?)"?$/', trim($line), $m)) {
                            $meta[$m[1]] = $m[2];
                        }
                    }
                    $posts[] = [
                        'title' => $meta['title'] ?? '',
                        'slug' => $meta['slug'] ?? '',
                        'excerpt' => $meta['excerpt'] ?? '',
                        'category' => $meta['category'] ?? '',
                        'date' => $meta['date'] ?? '',
                        'date_display' => $meta['date_display'] ?? '',
                        'reading_time' => $meta['reading_time'] ?? '',
                    ];
                }
            }

            usort($posts, fn ($a, $b) => $b['date'] <=> $a['date']);

            return array_slice($posts, 0, 3);
        });
    }
}
