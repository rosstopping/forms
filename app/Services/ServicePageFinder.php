<?php

namespace App\Services;

use App\Ai\Agents\ServicePageCoverageAnalyst;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ServicePageFinder
{
    public function __construct(private CompetitorPageFetcher $fetcher) {}

    /** @return array<string, mixed> */
    public function cachedFind(string $url): array
    {
        $key = 'service-page-coverage:v2:'.hash('sha256', strtolower((string) parse_url($url, PHP_URL_HOST)));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return $cached;
        }

        return Cache::lock($key.':lock', 240)->block(5, fn (): array => Cache::remember($key, now()->addDay(), fn (): array => $this->find($url)));
    }

    /** @return array<string, mixed> */
    public function find(string $url): array
    {
        $domain = preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
        $pending = [$url];
        $seen = [];
        $pages = [];
        $skipped = 0;
        $deadline = microtime(true) + 120;
        try {
            $sitemapLinks = array_values(array_filter($this->fetcher->sitemapLinks($url, $domain), fn (string $link): bool => $this->eligible($link, $domain)));
            usort($sitemapLinks, fn (string $a, string $b): int => $this->priority($b) <=> $this->priority($a));
            $pending = [...$pending, ...$sitemapLinks];
        } catch (Throwable) {
            // Navigation discovery remains available when a sitemap cannot be read.
        }
        while ($pending !== [] && count($pages) < 8 && count($seen) < 14 && microtime(true) < $deadline) {
            $next = array_shift($pending);
            if (isset($seen[$next])) {
                continue;
            }
            $seen[$next] = true;
            try {
                $page = $this->fetcher->fetch($next, $domain, includeLinks: true);
                $final = $page['final_url'];
                if (collect($pages)->contains('url', $final)) {
                    continue;
                }
                $pages[] = [
                    'id' => count($pages), 'url' => $final,
                    'title' => $page['title'] ?? '', 'headings' => $page['headings'] ?? [],
                    'text' => mb_substr($page['main_content'] ?? '', 0, 9000),
                ];
                $links = array_values(array_filter($page['discovered_links'] ?? [], fn (string $link): bool => $this->eligible($link, $domain)));
                usort($links, fn (string $a, string $b): int => $this->priority($b) <=> $this->priority($a));
                $pending = array_values(array_unique([...$pending, ...$links]));
                usort($pending, fn (string $a, string $b): int => $this->priority($b) <=> $this->priority($a));
                $pending = array_slice($pending, 0, 60);
            } catch (Throwable $exception) {
                if ($pages === []) {
                    throw new RuntimeException('We could not read the website. It may block automated checks.', previous: $exception);
                }
                $skipped++;
            }
        }
        if ($pages === []) {
            throw new RuntimeException('We could not read any website pages.');
        }
        $response = (new ServicePageCoverageAnalyst)->prompt(json_encode(['pages' => $pages], JSON_THROW_ON_ERROR), timeout: 55)->toArray();

        return $this->report($pages, $response, $pending !== [] || $skipped > 0);
    }

    /** @param array<int, array<string, mixed>> $pages
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    public function report(array $pages, array $response, bool $partial): array
    {
        $suggestions = [];
        $covered = [];
        $seen = [];
        foreach (($response['supported'] ?? false) ? array_slice($response['services'] ?? [], 0, 6) : [] as $service) {
            $source = $pages[$service['evidence_page_id'] ?? -1] ?? null;
            $name = Str::squish((string) ($service['service'] ?? ''));
            $quote = Str::squish((string) ($service['quote'] ?? ''));
            $key = Str::lower($name);
            if ($source === null || $name === '' || mb_strlen($name) > 100 || mb_strlen($quote) < 15 || mb_strlen($quote) > 350
                || isset($seen[$key]) || ! Str::contains(Str::lower(Str::squish($source['text'])), Str::lower($quote))) {
                continue;
            }
            $seen[$key] = true;
            $dedicated = array_values(array_filter($service['dedicated_page_ids'] ?? [], fn ($id): bool => is_int($id) && isset($pages[$id]) && $this->dedicatedUrl($pages[$id]['url'])));
            foreach ($pages as $page) {
                $headings = implode(' ', array_column(array_filter($page['headings'] ?? [], fn ($heading): bool => ($heading['level'] ?? '') === 'h1'), 'text'));
                if ($this->dedicatedUrl($page['url'])
                    && Str::contains(Str::lower($page['title'].' '.$headings), $key)) {
                    $dedicated[] = $page['id'];
                }
            }
            if ($dedicated !== []) {
                $covered[] = ['service' => $name, 'url' => $pages[$dedicated[0]]['url']];

                continue;
            }
            $title = Str::squish((string) ($service['suggested_title'] ?? ''));
            $suggestions[] = [
                'service' => $name, 'title' => mb_substr($title ?: $name, 0, 150),
                'quote' => $quote, 'source_url' => $source['url'],
                'questions' => array_slice(array_values(array_filter($service['questions'] ?? [], fn ($question): bool => is_string($question) && mb_strlen($question) <= 200)), 0, 3),
            ];
        }

        return [
            'suggestions' => $suggestions, 'covered' => $covered,
            'supported' => (bool) ($response['supported'] ?? false),
            'pages' => array_map(fn (array $page): array => ['url' => $page['url'], 'title' => $page['title']], $pages),
            'partial' => $partial, 'checked_at' => now()->toIso8601String(),
        ];
    }

    private function eligible(string $url, string $domain): bool
    {
        return in_array(strtolower((string) parse_url($url, PHP_URL_HOST)), [$domain, 'www.'.$domain], true)
            && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
            && parse_url($url, PHP_URL_QUERY) === null
            && ! preg_match('~\.(?:pdf|jpe?g|png|gif|svg|zip|xml|webp)$|/(?:cart|checkout|login|account|privacy|terms|tag|category|blog|news|journal|guides|case-studies)(?:/|$)~i', (string) parse_url($url, PHP_URL_PATH));
    }

    private function dedicatedUrl(string $url): bool
    {
        $path = trim((string) parse_url($url, PHP_URL_PATH), '/');

        return $path !== '' && ! preg_match('~^(?:blog|news|journal|guides|case-studies|about|contact)(?:/|$)~i', $path);
    }

    private function priority(string $url): int
    {
        return preg_match('~service|repair|install|maintenan|treatment|solution|develop|design|seo|consult~i', $url) ? 2 : (preg_match('~blog|news|journal~i', $url) ? 0 : 1);
    }
}
