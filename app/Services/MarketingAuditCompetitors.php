<?php

namespace App\Services;

use App\Services\DataForSEO\CompetitorsService;
use App\Services\DataForSEO\DomainIntersectionService;
use App\Services\DataForSEO\Exceptions\DataForSEOException;
use Illuminate\Support\Facades\Cache;

class MarketingAuditCompetitors
{
    public function __construct(private CompetitorsService $competitors, private DomainIntersectionService $intersection) {}

    /** @return array<string, mixed>|null */
    public function forDomain(string $domain, array $seo): ?array
    {
        if (($seo['organic_keywords'] ?? 0) < 1 || blank(config('services.dataforseo.login')) || blank(config('services.dataforseo.password'))) {
            return null;
        }

        $location = (int) $seo['location_code'];
        $language = (string) $seo['language_code'];
        $key = 'marketing-audit-competitors:v2:'.hash('sha256', strtolower($domain)."|{$location}|{$language}");

        $cached = Cache::get($key);
        if (is_array($cached)) {
            return isset($cached['unavailable']) ? null : $cached;
        }

        try {
            $response = $this->competitors->forDomain($domain, $location, $language, 5);
            $candidates = collect($response->competitors)->filter(fn ($item): bool => $item->commonKeywords > 0
                && $this->normaliseDomain($item->domain) !== $this->normaliseDomain($domain));
            $competitor = $candidates->first();
            if ($competitor === null) {
                Cache::put($key, [], now()->addDays(7));

                return [];
            }

            $shared = $this->intersection->compare($competitor->domain, $domain, $location, $language, true, 5);
            $terms = collect(data_get($shared->results, '0.items', []))
                ->filter(fn ($item): bool => is_array($item)
                    && filled(data_get($item, 'keyword_data.keyword'))
                    && (int) data_get($item, 'first_domain_serp_element.rank_group') > 0
                    && (int) data_get($item, 'second_domain_serp_element.rank_group') > 0)
                ->take(5)
                ->map(fn (array $item): array => [
                    'term' => (string) data_get($item, 'keyword_data.keyword'),
                    'competitor_position' => (int) data_get($item, 'first_domain_serp_element.rank_group'),
                    'our_position' => (int) data_get($item, 'second_domain_serp_element.rank_group'),
                    'monthly_searches' => data_get($item, 'keyword_data.keyword_info.search_volume'),
                ])->values()->all();

            $result = [
                'domain' => $competitor->domain,
                'shared_terms' => $competitor->commonKeywords,
                'terms' => $terms,
                'others' => $candidates
                    ->filter(fn ($item): bool => $item->commonKeywords > 0 && $item->domain !== $competitor->domain)
                    ->take(2)
                    ->map(fn ($item): array => ['domain' => $item->domain, 'shared_terms' => $item->commonKeywords])
                    ->values()->all(),
                'retrieved_at' => now()->toIso8601String(),
                'cost_usd' => round($response->cost + $shared->cost, 5),
            ];
            Cache::put($key, $result, now()->addDays(7));

            return $result;
        } catch (DataForSEOException $exception) {
            report($exception);
            Cache::put($key, ['unavailable' => true], now()->addHour());

            return null;
        }
    }

    private function normaliseDomain(string $domain): string
    {
        $host = parse_url(str_contains($domain, '://') ? $domain : 'https://'.$domain, PHP_URL_HOST);

        return preg_replace('/^www\./', '', strtolower(rtrim((string) $host, '.'))) ?? '';
    }
}
