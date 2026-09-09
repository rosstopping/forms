<?php

namespace App\Services;

use App\Ai\Agents\CompetitorAnalyst;
use App\Models\CompetitorAudit;
use App\Models\ContentRequest;
use App\Models\SeoKeyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class CompetitorBriefGenerator
{
    public function generate(CompetitorAudit $audit): void
    {
        $keywords = $audit->keywords()->where('search_intent', '!=', 'navigational')->orderByDesc('search_volume')->limit(100)->get()->keyBy('id');
        $pages = $audit->pages()->where('status', 'completed')->get();
        if ($pages->isEmpty() || $keywords->isEmpty()) {
            return;
        }
        $website = $audit->website;
        $ownPages = $website->healthReports()->where('status', 'completed')->latest('completed_at')->first()?->pages()->limit(40)->get(['url', 'title', 'meta_description']) ?? collect();
        $ownKeywords = SeoKeyword::query()->where('website_id', $website->id)->whereHas('snapshot', fn ($query) => $query->where('domain', $audit->domain)->where('location_code', $audit->location_code)->where('language_code', $audit->language_code))->latest('id')->limit(100)->get(['keyword', 'ranking_url']);
        $ownUrls = $ownPages->pluck('url')->merge($ownKeywords->pluck('ranking_url'))->filter()->unique();
        $context = [
            'website' => $website->name, 'domain' => $audit->domain,
            'audience' => Str::limit((string) $website->contentPlan?->audience, 4000, ''),
            'guidance' => Str::limit((string) $website->contentPlan?->guidance, 6000, ''),
            'own_pages' => $ownPages->all(), 'own_keywords' => $ownKeywords->all(),
            'queued_briefs' => ContentRequest::where('website_id', $website->id)->whereNotNull('competitor_fingerprint')->latest()->limit(30)->pluck('instructions')->all(),
            'keywords' => $keywords->values()->toArray(), 'pages' => $pages->map(fn ($page): array => ['url' => $page->url, 'analysis' => $page->analysis])->all(),
        ];
        $response = (new CompetitorAnalyst)->prompt(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), timeout: 120);
        $items = $this->normalizeItems($response['opportunities']);
        Validator::make(['opportunities' => $items], ['opportunities' => ['present', 'array', 'max:5'], 'opportunities.*' => ['array'], 'opportunities.*.title' => ['required', 'string', 'max:200'], 'opportunities.*.primary_keyword_id' => ['required', 'integer'], 'opportunities.*.keyword_ids' => ['present', 'array', 'max:10'], 'opportunities.*.source_urls' => ['required', 'array', 'min:1', 'max:5'], 'opportunities.*.relevance' => ['required', 'integer', 'between:1,3'], 'opportunities.*.existing_page_url' => ['present', 'nullable', 'string'], 'opportunities.*.improvements' => ['required', 'array', 'max:5'], 'opportunities.*.outline' => ['required', 'array', 'max:8'],
            'opportunities.*.keyword_ids.*' => ['integer'],
            'opportunities.*.source_urls.*' => ['string', 'max:2048'],
            'opportunities.*.search_intent' => ['required', 'string', 'max:100'],
            'opportunities.*.relevance_reason' => ['required', 'string', 'max:500'],
            'opportunities.*.content_format' => ['required', 'string', 'max:200'],
            'opportunities.*.observations' => ['present', 'array', 'max:5'],
            'opportunities.*.ranking_hypotheses' => ['present', 'array', 'max:3'],
            'opportunities.*.gaps' => ['present', 'array', 'max:5'],
            'opportunities.*.observations.*' => ['string', 'max:500'],
            'opportunities.*.ranking_hypotheses.*' => ['string', 'max:500'],
            'opportunities.*.gaps.*' => ['string', 'max:500'],
            'opportunities.*.improvements.*' => ['string', 'max:500'],
            'opportunities.*.outline.*' => ['string', 'max:250']])->validate();
        DB::transaction(function () use ($audit, $items, $keywords, $pages, $ownUrls): void {
            foreach ($items as $item) {
                $primary = $keywords->get($item['primary_keyword_id']);
                $ids = array_unique([$item['primary_keyword_id'], ...$item['keyword_ids']]);
                if (! $primary || collect($ids)->contains(fn ($id): bool => ! $keywords->has($id)) || array_diff($item['source_urls'], $pages->pluck('url')->all()) !== [] || ($item['existing_page_url'] && ! $ownUrls->contains($item['existing_page_url']))) {
                    continue;
                }
                if ($primary->search_intent === 'navigational' || Str::contains(Str::lower($primary->keyword), $audit->competitor_domain)) {
                    continue;
                }
                $fingerprint = hash('sha256', $audit->location_code.'|'.$audit->language_code.'|'.Str::lower(trim($primary->keyword)));
                $brief = [...$item, 'keywords' => $keywords->only($ids)->values()->toArray(), 'audit_id' => $audit->id, 'competitor_domain' => $audit->competitor_domain, 'our_domain' => $audit->domain, 'location_code' => $audit->location_code, 'language_code' => $audit->language_code, 'collected_at' => $audit->started_at?->toIso8601String(), 'data_source' => 'dataforseo_estimate'];
                $score = $item['relevance'] * 100 + min(50, (int) (log10(($primary->search_volume ?? 0) + 1) * 10)) + max(0, 30 - $primary->position);
                $audit->opportunities()->firstOrCreate(['fingerprint' => $fingerprint], ['website_id' => $audit->website_id, 'title' => $item['title'], 'priority_score' => $score, 'brief' => $brief]);
            }
        });
    }

    private function normalizeItems(mixed $items): mixed
    {
        if (! is_array($items)) {
            return $items;
        }

        $limits = [
            'keyword_ids' => 10,
            'source_urls' => 5,
            'observations' => 5,
            'ranking_hypotheses' => 3,
            'gaps' => 5,
            'improvements' => 5,
            'outline' => 8,
        ];

        return collect(array_slice(array_values($items), 0, 5))->map(function (mixed $item) use ($limits): mixed {
            if (! is_array($item)) {
                return $item;
            }

            $item['keyword_ids'] = is_array($item['keyword_ids'] ?? null) ? $item['keyword_ids'] : [];

            foreach ($limits as $field => $limit) {
                if (is_array($item[$field] ?? null)) {
                    $item[$field] = array_slice(array_values(array_unique($item[$field], SORT_REGULAR)), 0, $limit);
                }
            }

            return $item;
        })->all();
    }
}
