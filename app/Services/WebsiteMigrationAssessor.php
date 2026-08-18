<?php

namespace App\Services;

use Illuminate\Support\Str;

class WebsiteMigrationAssessor
{
    /**
     * @param  array<int, array<string, mixed>>  $pages
     * @return array{migration_difficulty: string, migration_difficulty_reason: string}
     */
    public function assess(int $pageCount, int $maximumPages, array $pages): array
    {
        if ($pageCount === 0) {
            return ['migration_difficulty' => 'unknown', 'migration_difficulty_reason' => 'No indexable pages could be confirmed.'];
        }

        $signals = collect($pages)->flatMap(fn (array $page): array => [
            Str::lower((string) ($page['url'] ?? '')),
            Str::lower((string) ($page['title'] ?? '')),
        ])->implode(' ');
        $hardSignal = Str::contains($signals, ['woocommerce', 'shopify', '/cart', '/checkout', '/customer', '/account', '/membership', '/member-', '/dashboard']);
        $mediumSignal = Str::contains($signals, ['/booking', '/book-', '/reservation', '/events', '/tag/', '/category/', '/archive']);

        if ($pageCount > 40 || $hardSignal) {
            return ['migration_difficulty' => 'hard', 'migration_difficulty_reason' => $pageCount > 40 ? "The site contains more than 40 indexable pages ({$pageCount} found)." : 'The crawl found ecommerce, account, or membership indicators.'];
        }

        if ($pageCount > $maximumPages || $pageCount > 20 || $mediumSignal) {
            return ['migration_difficulty' => 'medium', 'migration_difficulty_reason' => $pageCount > $maximumPages ? "The site exceeds this search's {$maximumPages}-page limit ({$pageCount} found)." : ($pageCount > 20 ? "The site contains approximately {$pageCount} indexable pages." : 'The crawl found booking or large archive indicators.')];
        }

        return ['migration_difficulty' => 'easy', 'migration_difficulty_reason' => "The site appears to be a small brochure website with approximately {$pageCount} indexable pages and no complex functionality detected."];
    }
}
