<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

class SitemapController extends Controller
{
    public function index(): Response
    {
        $entries = collect(config('marketing.navigation', []))
            ->map(function (array $item): array {
                return [
                    'loc' => route($item['route']),
                    'lastmod' => null,
                ];
            })
            ->values()
            ->all();

        $entries[] = [
            'loc' => route('solutions.colocation'),
            'lastmod' => null,
        ];

        foreach ($this->blogEntries() as $entry) {
            $entries[] = $entry;
        }

        return response()
            ->view('sitemap', ['entries' => $entries])
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }

    /**
     * @return array<int, array{loc: string, lastmod: string|null}>
     */
    private function blogEntries(): array
    {
        $postsPath = resource_path('blog/posts');
        $files = glob($postsPath.'/*.md') ?: [];
        $entries = [];

        foreach ($files as $file) {
            $content = file_get_contents($file);

            if (! is_string($content)) {
                continue;
            }

            if (! preg_match('/^---\n(.+?)\n---\n(.+)$/s', $content, $matches)) {
                continue;
            }

            $meta = [];

            foreach (explode("\n", $matches[1]) as $line) {
                if (preg_match('/^(\w+):\s*"?(.+?)"?$/', trim($line), $match)) {
                    $meta[$match[1]] = $match[2];
                }
            }

            $slug = $meta['slug'] ?? null;

            if (! $slug) {
                continue;
            }

            $entries[] = [
                'loc' => route('blog.show', $slug),
                'lastmod' => Carbon::createFromTimestampUTC(filemtime($file) ?: time())->toAtomString(),
            ];
        }

        return $entries;
    }
}
