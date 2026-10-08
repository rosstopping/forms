<?php

namespace App\Services;

use App\Ai\Agents\AuditBusinessProfiler;
use App\Ai\Agents\AuditOpportunitySelector;
use App\Services\DataForSEO\Data\RankedKeywordData;
use App\Services\DataForSEO\DataForSEOClient;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Throwable;

class MarketingAuditOpportunity
{
    public const VERSION = 1;

    public function __construct(private DataForSEOClient $client) {}

    /** @param array<string, mixed>|null $seo
     * @return array<string, mixed>
     */
    public function forSite(string $domain, string $context, ?array $seo): array
    {
        $unavailable = ['version' => self::VERSION, 'status' => 'unavailable', 'projection' => null];
        if ($seo === null || mb_strlen($context) < 80 || blank(config('services.dataforseo.login')) || blank(config('services.dataforseo.password')) || blank(config('ai.providers.'.config('ai.default', 'openai').'.key'))) {
            return [...$unavailable, 'reason' => 'Business or search evidence unavailable.'];
        }
        $context = mb_substr($context, 0, 12000);
        $key = 'marketing-audit-opportunity:v'.self::VERSION.':'.hash('sha256', strtolower($domain).'|'.$seo['location_code'].'|'.$seo['language_code'].'|'.$context.'|'.($seo['retrieved_at'] ?? ''));
        $cached = Cache::get($key);
        if (is_array($cached)) {
            return [...$cached, 'cached' => true];
        }
        $lock = Cache::lock($key.':lock', 180);
        if (! $lock->get()) {
            return [...$unavailable, 'reason' => 'Matching research is already in progress.'];
        }
        try {
            $result = $this->research($domain, $context, $seo);
            Cache::put($key, $result, now()->addDays($result['status'] === 'ready' ? 7 : 1));

            return $result;
        } catch (Throwable $exception) {
            report($exception);
            $result = [...$unavailable, 'reason' => 'Opportunity research could not be completed.'];
            Cache::put($key, $result, now()->addHour());

            return $result;
        } finally {
            $lock->release();
        }
    }

    /** @param array<string, mixed> $seo
     * @return array<string, mixed>
     */
    private function research(string $domain, string $context, array $seo): array
    {
        $profileResponse = (new AuditBusinessProfiler)->prompt(json_encode(['domain' => $domain, 'homepage' => $context], JSON_THROW_ON_ERROR), timeout: 25);
        $profile = $profileResponse->toArray();
        $evidence = ['version' => self::VERSION, 'status' => 'unavailable', 'projection' => null, 'retrieved_at' => now()->toIso8601String(), 'profile' => $profile, 'requests' => [], 'ai' => ['profile' => ['meta' => $profileResponse->meta->toArray(), 'usage' => $profileResponse->usage->toArray()]]];
        if (! ($profile['supported'] ?? false) || ! in_array($profile['market'] ?? '', ['local', 'national'], true) || ! $this->quoted($context, $profile['market_evidence'] ?? '')) {
            return [...$evidence, 'reason' => 'Business market needs a personal review.'];
        }
        if ($profile['market'] === 'local' && (! filled($profile['location'] ?? null) || ! Str::contains(Str::lower($context), Str::lower($profile['location'])))) {
            return [...$evidence, 'reason' => 'Service area needs a personal review.'];
        }
        $seeds = collect($profile['seeds'] ?? [])->filter(fn (array $seed): bool => filled($seed['term'] ?? null) && $this->quoted($context, $seed['evidence'] ?? '')
            && ($profile['market'] !== 'local' || Str::contains(Str::lower($seed['term']), Str::lower($profile['location']))))->unique('term')->take(2);
        if ($seeds->isEmpty()) {
            return [...$evidence, 'reason' => 'No evidenced customer searches.'];
        }
        $candidates = [];
        foreach ($seeds as $seed) {
            $items = $this->request('serp/google/organic/live/advanced', ['keyword' => $seed['term'], 'device' => 'desktop', 'depth' => 10], $seo, $evidence);
            foreach ($items as $item) {
                $candidate = $this->domain((string) ($item['domain'] ?? ''));
                if (($item['type'] ?? '') !== 'organic' || $candidate === null || $candidate === $this->domain($domain) || $this->excluded($candidate)) {
                    continue;
                }
                $snippet = Str::limit(($item['title'] ?? '').' '.($item['description'] ?? ''), 600, '');
                $candidates[$candidate] ??= ['domain' => $candidate, 'snippets' => [], 'keywords' => []];
                $candidates[$candidate]['snippets'][] = ['seed' => $seed['term'], 'text' => $snippet, 'url' => $item['url'] ?? null];
            }
        }
        $candidates = array_slice($candidates, 0, 5, true);
        if (count($candidates) < 3) {
            return [...$evidence, 'reason' => 'Too few candidate comparison sites.', 'candidates' => array_values($candidates)];
        }
        $terms = [];
        foreach ($candidates as $candidateDomain => &$candidate) {
            $items = $this->request('dataforseo_labs/google/ranked_keywords/live', [
                'target' => $candidateDomain, 'item_types' => ['organic'], 'limit' => 30,
                'filters' => ['ranked_serp_element.serp_item.rank_group', '<=', 20],
                'order_by' => ['keyword_data.keyword_info.search_volume,desc'],
            ], $seo, $evidence);
            foreach ($items as $item) {
                $keyword = RankedKeywordData::fromArray($item);
                if ($keyword === null || ($keyword->searchVolume ?? 0) <= 0 || $keyword->locationCode !== (int) $seo['location_code'] || $keyword->languageCode !== $seo['language_code'] || $keyword->position < 1 || $keyword->position > 20) {
                    continue;
                }
                $term = Str::lower(Str::squish($keyword->keyword));
                if ($profile['market'] === 'local' && ! Str::contains($term, Str::lower($profile['location']))) {
                    continue;
                }
                $terms[$term] ??= ['id' => count($terms), 'term' => $term, 'monthly_searches' => $keyword->searchVolume, 'difficulty' => $keyword->keywordDifficulty, 'observations' => []];
                $terms[$term]['observations'][$candidateDomain] = ['position' => $keyword->position, 'url' => $keyword->rankingUrl, 'estimated_visits' => $keyword->estimatedTraffic];
                $candidate['keywords'][] = $terms[$term]['id'];
            }
        }
        unset($candidate);
        $terms = collect($terms)->filter(fn (array $term): bool => count($term['observations']) >= 2)->keyBy('id')->all();
        if ($terms === []) {
            return [...$evidence, 'reason' => 'No shared relevant demand.', 'candidates' => array_values($candidates)];
        }
        $ownItems = $this->request('dataforseo_labs/google/ranked_keywords/live', [
            'target' => $domain, 'item_types' => ['organic'], 'limit' => 150,
            'filters' => ['keyword_data.keyword', 'in', array_column($terms, 'term')],
        ], $seo, $evidence);
        $ownRankings = [];
        foreach ($ownItems as $item) {
            $keyword = RankedKeywordData::fromArray($item);
            if ($keyword !== null && $keyword->locationCode === (int) $seo['location_code'] && $keyword->languageCode === $seo['language_code']) {
                $ownRankings[] = ['term' => $keyword->keyword, 'position' => $keyword->position, 'monthly_searches' => $keyword->searchVolume, 'url' => $keyword->rankingUrl, 'estimated_visits' => $keyword->estimatedTraffic];
            }
        }
        $seo['keywords'] = $ownRankings;
        $selectionResponse = (new AuditOpportunitySelector)->prompt(json_encode(['profile' => $profile, 'candidates' => array_values($candidates), 'keywords' => array_values($terms), 'own_rankings' => $seo['keywords'] ?? []], JSON_THROW_ON_ERROR), timeout: 25);
        $selection = $selectionResponse->toArray();
        $evidence['ai']['selection'] = ['meta' => $selectionResponse->meta->toArray(), 'usage' => $selectionResponse->usage->toArray()];
        $comparables = collect($selection['comparables'] ?? [])->filter(function (array $comparison) use ($candidates): bool {
            $candidate = $candidates[$comparison['domain'] ?? ''] ?? null;

            return $candidate !== null && filled($comparison['reason'] ?? null) && $this->quoted(implode(' ', array_column($candidate['snippets'], 'text')), $comparison['evidence'] ?? '');
        })->unique('domain')->take(5)->values()->all();
        $evidence = [...$evidence, 'candidates' => array_values($candidates), 'comparables' => $comparables, 'keywords' => array_values($terms), 'own_rankings' => $ownRankings];
        if (count($comparables) < 3) {
            return [...$evidence, 'reason' => 'Fewer than three supported comparable businesses.'];
        }
        $projection = $this->calculate([...$seo, 'brand_terms' => [$profile['name'], explode('.', $domain)[0]]], $terms, $comparables, $selection['groups'] ?? []);

        return [...$evidence, 'status' => $projection === null ? 'unavailable' : 'ready', 'projection' => $projection, 'reason' => $projection === null ? 'Insufficient achievable page opportunities.' : null];
    }

    /** @param array<string, mixed> $seo
     * @param  array<int, array<string, mixed>>  $terms
     * @param  array<int, array<string, mixed>>  $comparables
     * @param  array<int, array<string, mixed>>  $groups
     * @return array<string, mixed>|null
     */
    public function calculate(array $seo, array $terms, array $comparables, array $groups): ?array
    {
        $domains = array_column($comparables, 'domain');
        if (count(array_unique($domains)) < 3 || ! is_numeric($seo['estimated_monthly_visits'] ?? null)) {
            return null;
        }
        $ownTerms = collect($seo['keywords'] ?? [])->keyBy(fn (array $keyword): string => Str::lower(Str::squish($keyword['term'])));
        $brandTokens = collect($domains)->map(fn (string $domain): string => explode('.', $domain)[0])
            ->merge($seo['brand_terms'] ?? [])->filter(fn (string $brand): bool => strlen($brand) >= 3)
            ->map(fn (string $brand): string => preg_quote($brand, '/'))->implode('|');
        $used = [];
        $seenPages = [];
        $pages = [];
        $lowerGain = $upperGain = 0.0;
        $existingSelectedVisits = 0.0;
        $benchmark = array_fill_keys($domains, 0.0);
        foreach (array_slice($groups, 0, 6) as $group) {
            if (! filled($group['label'] ?? null) || ! filled($group['reason'] ?? null)) {
                continue;
            }
            $eligible = [];
            foreach (array_slice($group['keyword_ids'] ?? [], 0, 10) as $id) {
                $term = $terms[$id] ?? null;
                if ($term === null || isset($used[$id]) || ($brandTokens !== '' && preg_match('/\b('.$brandTokens.')\b/i', $term['term']))) {
                    continue;
                }
                $observations = array_intersect_key($term['observations'], $benchmark);
                $own = $ownTerms->get($term['term']);
                $existing = $own !== null && ($own['position'] ?? 101) <= 30;
                if (count($observations) < 2 || (! $existing && (! is_numeric($term['difficulty'] ?? null) || $term['difficulty'] > 40))) {
                    continue;
                }
                $eligible[] = [...$term, 'existing' => $existing, 'own_position' => $own['position'] ?? null, 'own_estimated_visits' => $own['estimated_visits'] ?? null, 'observations' => $observations];
            }
            if ($eligible === []) {
                continue;
            }
            foreach ($eligible as $term) {
                $used[$term['id']] = true;
            }
            $primary = collect($eligible)->sortByDesc('monthly_searches')->first();
            $sharedPages = 0;
            foreach ($primary['observations'] as $domain => $observation) {
                if (isset($seenPages[$domain][$observation['url'] ?? ''])) {
                    $sharedPages++;
                }
            }
            if ($sharedPages >= 2) {
                continue;
            }
            $volume = (int) $primary['monthly_searches'];
            $currentCtr = is_numeric($primary['own_estimated_visits']) ? max(0, $primary['own_estimated_visits']) / $volume : ($primary['own_position'] !== null ? $this->ctr((int) $primary['own_position']) : 0.0);
            $observedCtr = collect($primary['observations'])->pluck('estimated_visits')->filter(fn (mixed $value): bool => is_numeric($value) && $value > 0)->median();
            if ($observedCtr === null) {
                continue;
            }
            $observedCtr /= $volume;
            $upperCtr = min($observedCtr, $primary['existing'] ? 0.04 : (($seo['organic_keywords'] ?? 0) < 20 ? 0.01 : 0.02));
            $lowerCtr = min($upperCtr, $primary['existing'] ? 0.0075 : 0.003);
            $lowerGain += max(0, ($lowerCtr - $currentCtr) * $volume);
            $upperGain += max(0, ($upperCtr - $currentCtr) * $volume);
            $existingSelectedVisits += $currentCtr * $volume;
            foreach ($domains as $domain) {
                $benchmark[$domain] += max(0, (float) ($primary['observations'][$domain]['estimated_visits'] ?? 0));
                $url = $primary['observations'][$domain]['url'] ?? null;
                if (filled($url)) {
                    $seenPages[$domain][$url] = true;
                }
            }
            $pages[] = ['label' => $group['label'], 'reason' => $group['reason'], 'keywords' => $eligible, 'modelled_search_volume' => $volume, 'lower_ctr' => $lowerCtr, 'upper_ctr' => $upperCtr, 'current_ctr' => $currentCtr, 'work' => $primary['existing'] ? 'Improve an existing ranking page' : 'Review coverage and improve or create a relevant page'];
        }
        if ($pages === [] || $upperGain < 1) {
            return null;
        }
        $baseline = max(0, (int) $seo['estimated_monthly_visits']);
        $ceiling = max(0, (float) collect($benchmark)->median() - $existingSelectedVisits);
        $upper = $baseline + min($upperGain, $ceiling);
        $lower = min($baseline + $lowerGain, $upper);
        if ($upper <= $baseline) {
            return null;
        }
        $step = $upper >= 1000 ? 100 : ($upper >= 100 ? 10 : 1);
        $roundedLower = (int) (floor($lower / $step) * $step);
        $roundedUpper = (int) (floor($upper / $step) * $step);
        if ($roundedUpper <= $roundedLower) {
            return null;
        }

        return [
            'model' => 'comparable_pages_v1', 'baseline_monthly_visits' => $baseline,
            'six_month_low' => $roundedLower,
            'six_month_high' => $roundedUpper,
            'pages' => $pages, 'comparable_page_visits' => $benchmark, 'benchmark_ceiling' => $ceiling,
            'method' => 'Planning scenario for up to six priority pages over six months, subject to scope, access and review. Uses the largest measured search volume per intent group to reduce duplicate demand; counts only incremental clicks over sampled existing rankings. Assumed click rates: 0.75–4% for existing opportunities; 0.3–1% for new topics on sites with fewer than 20 observed ranking terms, otherwise up to 2%. Upper rates are capped by observed comparable click estimates. Additional visits are capped at median comparable visits for the selected searches minus existing selected-search visits; the wider existing baseline is counted once. These assumptions are not calibrated outcome probabilities. Excludes Maps, paid ads and AI traffic; rankings, implementation and retained baseline traffic are not guaranteed.',
        ];
    }

    private function ctr(int $position): float
    {
        return match (true) {
            $position <= 3 => 0.1, $position <= 10 => 0.04, $position <= 20 => 0.0075, $position <= 30 => 0.003, default => 0.0,
        };
    }

    /** @param array<string, mixed> $task
     * @param  array<string, mixed>  $seo
     * @param  array<string, mixed>  $evidence
     * @return array<int, array<string, mixed>>
     */
    private function request(string $endpoint, array $task, array $seo, array &$evidence): array
    {
        $response = $this->client->post($endpoint, [...$task, 'location_code' => $seo['location_code'], 'language_code' => $seo['language_code']], 10);
        $evidence['requests'][] = ['endpoint' => $endpoint, 'task_id' => $response->taskId, 'cost_usd' => $response->cost, 'target' => $task['target'] ?? $task['keyword']];
        $items = data_get($response->results, '0.items', []);

        return is_array($items) ? array_values(array_filter($items, 'is_array')) : [];
    }

    private function quoted(string $text, string $quote): bool
    {
        return mb_strlen(trim($quote)) >= 8 && Str::contains(Str::lower($text), Str::lower(trim($quote)));
    }

    private function domain(string $domain): ?string
    {
        $domain = Str::lower(preg_replace('/^www\./i', '', trim($domain)) ?? '');

        return filter_var($domain, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) && str_contains($domain, '.') ? $domain : null;
    }

    private function excluded(string $domain): bool
    {
        return Str::contains($domain, ['yell.com', 'checkatrade.com', 'trustatrader.com', 'bark.com', 'wikipedia.org', 'amazon.', 'ebay.', 'facebook.com', 'instagram.com', 'youtube.com', 'tripadvisor.', 'yelp.', 'trustpilot.']);
    }
}
