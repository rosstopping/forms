<?php

namespace App\Services;

use App\Models\ContentGeneration;
use App\Models\ContentRequest;
use App\Models\SeoImpact;
use App\Models\Website;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use InvalidArgumentException;

class SeoImpactTracker
{
    public function forRequest(ContentRequest $request): SeoImpact
    {
        $request->loadMissing(['searchOpportunity', 'seoOpportunity.keyword']);
        $source = $request->searchOpportunity ?? $request->seoOpportunity;
        $url = $request->searchOpportunity?->page ?? data_get($request->seoOpportunity?->metrics, 'ranking_url');
        $query = $request->searchOpportunity?->query ?? $request->seoOpportunity?->keyword?->keyword ?? data_get($request->competitor_context, 'primary_keyword');
        preg_match_all('~https?://[^\s<>"\)]+~i', $request->instructions, $matches);
        $urls = $url ? [$url] : array_values(array_unique(array_map(fn (string $value): string => rtrim($value, '.,;'), $matches[0])));
        $urls = $this->websiteUrls($request->website, $urls);

        return SeoImpact::firstOrCreate(['website_id' => $request->website_id, 'source_key' => 'request:'.$request->id], [
            'content_request_id' => $request->id,
            'content_generation_id' => $request->content_generation_id,
            'title' => Str::limit($source?->title ?? $request->instructions, 200, ''),
            'hypothesis' => Str::limit($source?->recommendation ?? $request->instructions, 2000, ''),
            'target_urls' => $urls,
            'target_queries' => $query && mb_strlen($query) <= 100 ? [$query] : [],
            'primary_metric' => str_contains($source?->type ?? '', 'ctr') ? 'ctr' : 'clicks',
            'business_value' => in_array(data_get($source?->metrics, 'search_intent'), ['commercial', 'transactional'], true) ? 5 : 3,
            'confidence' => $request->searchOpportunity ? 4 : 2,
            'effort' => str_contains($source?->type ?? '', 'ctr') ? 2 : 3,
            'evidence' => ['source' => $request->searchOpportunity ? 'search_console' : ($request->seoOpportunity ? 'third_party_estimate' : 'editorial_brief'), 'observation' => $source?->summary, 'metrics' => $source?->metrics],
            'next_measurement_at' => $urls !== [] ? now() : null,
        ]);
    }

    public function forGeneration(ContentGeneration $generation): void
    {
        foreach ($generation->contentRequests as $request) {
            $this->forRequest($request)->update(['content_generation_id' => $generation->id]);
        }
        if ($generation->contentRequests->isNotEmpty()) {
            return;
        }
        $target = collect($generation->target_keyword_context ?? [])->firstWhere('id', $generation->seo_target_keyword_id);
        $urls = $this->websiteUrls($generation->plan->website, empty($target['ranking_url']) ? [] : [$target['ranking_url']]);
        SeoImpact::firstOrCreate(['website_id' => $generation->plan->website_id, 'source_key' => 'generation:'.$generation->id], [
            'content_generation_id' => $generation->id,
            'title' => Str::limit($target ? 'Improve search visibility for '.$target['term'] : 'Focused content improvement', 200, ''),
            'hypothesis' => 'Improve existing coverage for the intended search need, using verified business facts and relevant internal links.',
            'target_urls' => $urls,
            'target_queries' => $target && mb_strlen($target['term']) <= 100 ? [$target['term']] : [],
            'evidence' => ['source' => 'strategic_target', 'target' => $target],
            'next_measurement_at' => $urls === [] ? null : now(),
        ]);
    }

    /** @return Collection<int, string> */
    public function protectedKeys(Website $website): Collection
    {
        return SeoImpact::where('website_id', $website->id)->whereIn('status', ['measuring', 'review_required'])
            ->get(['target_urls', 'target_queries', 'control_url'])->flatMap(fn (SeoImpact $impact): array => [
                ...array_map(fn (string $url): string => $this->urlKey($url), [...$impact->target_urls, ...($impact->control_url ? [$impact->control_url] : [])]),
                ...array_map(fn (string $term): string => 'term:'.mb_strtolower(trim($term)), $impact->target_queries),
            ])->filter()->unique()->values();
    }

    public function urlKey(string $url): string
    {
        try {
            return 'url:'.app(PixelUrlNormalizer::class)->normalizeForMatch($url);
        } catch (InvalidArgumentException) {
            return '';
        }
    }

    /** @return list<string> */
    public function websiteUrls(Website $website, array $urls): array
    {
        $normalizer = app(PixelUrlNormalizer::class);
        $hosts = $website->domains->map(fn ($domain): string => $normalizer->normalizeHost($domain->domain));

        return collect($urls)->filter(function (string $url) use ($hosts, $normalizer): bool {
            if (mb_strlen($url) > 700 || ! filter_var($url, FILTER_VALIDATE_URL) || ! in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)
                || parse_url($url, PHP_URL_USER) || parse_url($url, PHP_URL_FRAGMENT)) {
                return false;
            }
            try {
                return $hosts->contains($normalizer->normalizeHost((string) parse_url($url, PHP_URL_HOST)));
            } catch (InvalidArgumentException) {
                return false;
            }
        })->unique()->take(5)->values()->all();
    }

    public function requestIsProtected(ContentRequest $request): bool
    {
        $keys = $this->protectedKeys($request->website);
        $impact = $request->seoImpact;
        preg_match_all('~https?://[^\s<>"\)]+~i', $request->instructions, $matches);
        $urls = [...($impact?->target_urls ?? []), ...array_map(fn (string $url): string => rtrim($url, '.,;'), $matches[0])];

        return $keys->intersect(array_map($this->urlKey(...), $urls))->isNotEmpty()
            || $keys->intersect(array_map(fn (string $query): string => 'term:'.mb_strtolower(trim($query)), $impact?->target_queries ?? []))->isNotEmpty();
    }

    public function promptContext(ContentGeneration $generation): string
    {
        $current = SeoImpact::where('content_generation_id', $generation->id)->get();
        $history = SeoImpact::where('website_id', $generation->plan->website_id)
            ->whereNotNull('live_at')->latest('live_at')->limit(10)->get();
        $rows = $current->merge($history)->unique('id')->map(fn (SeoImpact $impact): array => [
            'id' => $impact->id, 'objective' => $impact->title, 'hypothesis' => $impact->hypothesis,
            'target_urls' => $impact->target_urls, 'query_group' => $impact->target_queries,
            'primary_metric' => $impact->primary_metric, 'status' => $impact->status,
            'baseline' => data_get($impact->baseline, 'target.totals'), 'live_at' => $impact->live_at?->toIso8601String(),
            'observed_outcome' => $impact->outcome, 'decision' => $impact->decision, 'learning' => $impact->decision_notes,
        ])->all();

        return Str::limit(json_encode($rows, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), 5000, '\n[Impact history truncated.]');
    }
}
