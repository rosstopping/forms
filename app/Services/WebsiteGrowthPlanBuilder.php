<?php

namespace App\Services;

use App\Ai\Agents\WebsiteGrowthPlanner;
use App\Models\ContentRequest;
use App\Models\SearchConsoleMetric;
use App\Models\SeoTargetKeyword;
use App\Models\Website;
use App\Models\WebsiteAudit;
use App\Models\WebsiteSetup;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteGrowthPlanBuilder
{
    public function __construct(private SeoImpactTracker $tracker) {}

    /** @return array<string, mixed> */
    public function snapshot(Website $website): array
    {
        $brief = $this->defaults($website);
        $report = $this->baseline($website, $brief);
        $content = $website->contentPlan;
        $health = $website->healthReports()->where('status', 'completed')->latest('completed_at')->first();
        $pages = $health?->pages()->orderBy('depth')->limit(30)->get(['url', 'title', 'meta_description', 'is_indexable', 'checks'])->map(fn ($page): array => [
            'url' => $page->url, 'title' => Str::limit((string) $page->title, 300, ''),
            'meta_description' => Str::limit((string) $page->meta_description, 1000, ''), 'is_indexable' => $page->is_indexable,
            'findings' => collect($page->checks)->whereIn('status', ['failed', 'warning'])->take(5)->map(fn (array $check): array => [
                'label' => Str::limit((string) ($check['label'] ?? ''), 200, ''), 'message' => Str::limit((string) ($check['message'] ?? ''), 500, ''), 'status' => $check['status'],
            ])->values()->all(),
        ])->all() ?? [];
        $domain = $website->primaryDomain()?->domain;
        $snapshot = $website->seoSnapshots()->where('domain', $domain)
            ->where('location_code', config('services.dataforseo.location_code'))
            ->where('language_code', config('services.dataforseo.language_code'))
            ->whereIn('status', ['completed', 'completed_with_errors'])->latest('snapshot_date')->first();
        $rankedKeywords = $snapshot?->keywords()->where('website_id', $website->id)->where('location_code', config('services.dataforseo.location_code'))->where('language_code', config('services.dataforseo.language_code'))->whereBetween('position', [1, 100])->orderByDesc('search_volume')->limit(30)
            ->get(['keyword', 'position', 'ranking_url', 'search_volume', 'keyword_difficulty', 'search_intent'])->toArray() ?? [];
        $connection = $website->searchConsoleConnection;
        $metrics = $connection?->property_url ? SearchConsoleMetric::query()->whereBelongsTo($website)
            ->where('search_console_connection_id', $connection->id)->where('property_url', $connection->property_url)
            ->where('month', '>=', now()->subMonths(6)->startOfMonth())->where('month', '<', now()->startOfMonth()) : null;
        $siteHistory = $metrics ? (clone $metrics)->where('dimension_key', SearchConsoleMetric::SITE_DIMENSION_KEY)
            ->orderBy('month')->get(['month', 'clicks', 'impressions', 'position'])->toArray() : [];
        $latestMonth = $metrics ? (clone $metrics)->max('month') : null;
        $queries = $latestMonth ? (clone $metrics)->where('month', $latestMonth)->whereNotNull('query')
            ->orderByDesc('impressions')->limit(30)->get(['query', 'clicks', 'impressions', 'position', 'month'])->toArray() : [];
        $keywords = collect($report['keywords'])->keyBy(fn (array $keyword): string => SeoTargetKeyword::normalize($keyword['term']));
        foreach ($rankedKeywords as $keyword) {
            $key = SeoTargetKeyword::normalize($keyword['keyword']);
            if (! $keywords->has($key) && $keywords->count() >= 20) {
                continue;
            }
            $row = $keywords->get($key, ['term' => $keyword['keyword'], 'intended_url' => null, 'assignment_role' => 'supporting', 'baseline' => null]);
            if (! $row['baseline'] && $snapshot->snapshot_date->gte(now()->subDays(30)->startOfDay())) {
                $row['baseline'] = ['status' => 'ranked', 'position' => $keyword['position'], 'ranking_url' => $keyword['ranking_url'],
                    'observed_at' => $snapshot->snapshot_date->toIso8601String(), 'source' => 'Google search estimate',
                    'location_code' => $snapshot->location_code, 'language_code' => $snapshot->language_code, 'device' => 'desktop',
                    'market_label' => $snapshot->location_code === 2826 ? 'United Kingdom' : 'Selected search market'];
            }
            $keywords->put($key, [...$row, 'search_volume' => $keyword['search_volume'], 'keyword_difficulty' => $keyword['keyword_difficulty']]);
        }
        foreach ($queries as $query) {
            $key = SeoTargetKeyword::normalize($query['query']);
            if (! $keywords->has($key) && $keywords->count() < 20) {
                $keywords->put($key, ['term' => $query['query'], 'intended_url' => null, 'assignment_role' => 'supporting', 'baseline' => null,
                    'search_console' => $query]);
            } elseif ($keywords->has($key)) {
                $keywords->put($key, [...$keywords->get($key), 'search_console' => $query]);
            }
        }
        $report['keywords'] = $keywords->values()->map(fn (array $keyword, int $index): array => [...$keyword, 'keyword_id' => $index])->all();
        $tracker = $this->tracker;
        $ownUrls = collect($pages)->pluck('url')->concat(collect($rankedKeywords)->pluck('ranking_url'))
            ->concat(collect($report['keywords'])->pluck('intended_url'))->filter()
            ->filter(fn (string $url): bool => $tracker->websiteUrls($website, [$url]) !== [])->unique()->values()->all();
        $audit = WebsiteAudit::query()->where('website_id', $website->id)->where('domain', $domain)
            ->where('status', 'completed')->latest('completed_at')->first(['insights']);

        return [
            'baseline_report' => $report,
            'saved_business_brief' => array_diff_key($brief, ['keywords' => true]),
            'saved_business_context' => Str::limit((string) data_get($audit?->insights, 'business_context'), 10000, ''),
            'content_guidance' => ['audience' => Str::limit((string) $content?->audience, 3000, ''), 'guidance' => Str::limit((string) $content?->guidance, 8000, ''),
                'enabled' => $content?->enabled ?? false, 'monthly_article_limit' => $content?->monthly_article_limit, 'monthly_optimisation_limit' => $content?->monthly_optimisation_limit],
            'pages' => $pages, 'own_page_urls' => $ownUrls,
            'search_console' => ['site_history' => $siteHistory, 'recent_queries' => $queries,
                'note' => 'Saved completed-month property-scoped samples; positions are impression-weighted averages, not current ranks. Missing queries are not proof of no visibility.'],
            'seo_snapshot' => $snapshot ? ['date' => $snapshot->snapshot_date->toDateString(), 'summary' => $snapshot->only(['organic_keywords', 'estimated_organic_traffic', 'backlinks', 'referring_domains']), 'keywords' => $rankedKeywords] : null,
            'existing_work' => ContentRequest::query()->whereBelongsTo($website)->latest('id')->limit(15)
                ->get(['instructions', 'work_type', 'planning_status', 'planned_for', 'manual_completed_at'])
                ->map(fn (ContentRequest $request): array => [...$request->only(['work_type', 'planning_status', 'planned_for', 'manual_completed_at']), 'instructions' => Str::limit($request->instructions, 1500, '')])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function generate(array $context): array
    {
        $response = (new WebsiteGrowthPlanner)->prompt(json_encode($context, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), timeout: 120)->toArray();
        $rules = [
            'headline' => ['required', 'string', 'max:70'],
            'priorities' => ['required', 'array', 'size:3'],
            'priorities.*.title' => ['required', 'string', 'max:50'],
            'priorities.*.action' => ['required', 'string', 'max:160'],
            'priorities.*.outcome' => ['required', 'string', 'max:80'],
            'focus_keyword_ids' => ['present', 'array', 'max:6'], 'focus_keyword_ids.*' => ['integer', 'distinct'],
            'summary' => ['required', 'string', 'max:240'], 'success_measure' => ['required', 'string', 'max:150'],
            'dependencies' => ['required', 'string', 'max:150'], 'limitations' => ['required', 'string', 'max:200'],
            'roadmap' => ['required', 'array', 'size:6'], 'roadmap.*' => ['required', 'string', 'max:140'],
            'keyword_actions' => ['present', 'array', 'max:20'], 'keyword_actions.*.keyword_id' => ['required', 'integer', 'distinct'],
            'keyword_actions.*.action' => ['required', 'string', 'max:160'],
            'keyword_actions.*.target_best' => ['required', 'integer', 'between:0,100'],
            'keyword_actions.*.target_worst' => ['required', 'integer', 'between:0,100'],
            'keyword_actions.*.target_reason' => ['present', 'nullable', 'string', 'max:1500'],
            'suggested_keywords' => ['present', 'array', 'max:5'],
            'suggested_keywords.*.term' => ['required', 'string', 'max:255'],
            'suggested_keywords.*.intended_url' => ['present', 'nullable', 'string', 'max:700'],
            'suggested_keywords.*.action' => ['required', 'string', 'max:160'],
        ];
        foreach (['services', 'locations', 'audience', 'difference'] as $field) {
            $rules[$field] = ['required', 'string', 'max:2000'];
        }
        Validator::make($response, $rules)->validate();
        $report = $context['baseline_report'];
        $actions = collect($response['keyword_actions'])->keyBy('keyword_id');
        if ($actions->keys()->sort()->values()->all() !== collect($report['keywords'])->pluck('keyword_id')->sort()->values()->all()) {
            throw ValidationException::withMessages(['keyword_actions' => 'The generated plan must cover exactly the saved keyword candidates.']);
        }
        if (array_diff($response['focus_keyword_ids'], $actions->keys()->all()) !== []) {
            throw ValidationException::withMessages(['focus_keyword_ids' => 'Choose focus keywords from the saved candidate IDs.']);
        }
        $report['format_version'] = 2;
        $report['keywords'] = collect($report['keywords'])->map(function (array $keyword) use ($actions): array {
            $action = $actions->get($keyword['keyword_id']);
            $hasTarget = $keyword['baseline'] && $action['target_best'] > 0 && $action['target_worst'] >= $action['target_best'] && filled($action['target_reason']);

            return [...$keyword, 'action' => $action['action'], 'target_best' => $hasTarget ? $action['target_best'] : null,
                'target_worst' => $hasTarget ? $action['target_worst'] : null, 'target_reason' => $hasTarget ? $action['target_reason'] : null];
        })->all();
        $terms = collect($report['keywords'])->pluck('term')->map(fn (string $term): string => SeoTargetKeyword::normalize($term));
        foreach ($response['suggested_keywords'] as $suggestion) {
            $term = SeoTargetKeyword::normalize($suggestion['term']);
            if ($terms->contains($term) || ($suggestion['intended_url'] && ! in_array($suggestion['intended_url'], $context['own_page_urls'], true))) {
                continue;
            }
            $report['keywords'][] = [...$suggestion, 'assignment_role' => 'supporting', 'suggested' => true, 'baseline' => null, 'target_best' => null, 'target_worst' => null, 'target_reason' => null];
            $terms->push($term);
        }
        foreach (['headline', 'summary', 'priorities', 'focus_keyword_ids', 'success_measure', 'dependencies', 'limitations', 'roadmap'] as $field) {
            $report[$field] = $response[$field];
        }
        $report['business'] = array_intersect_key($response, array_flip(['services', 'locations', 'audience', 'difference']));
        $report['search_console'] = $context['search_console'];

        return $report;
    }

    /** @return array<string, mixed> */
    private function defaults(Website $website): array
    {
        $setup = WebsiteSetup::query()->where('website_id', $website->id)->first()?->data ?? [];

        return [
            'services' => data_get($setup, 'business.services', ''),
            'locations' => data_get($setup, 'business.locations', ''),
            'audience' => data_get($setup, 'business.audience', ''),
            'difference' => data_get($setup, 'business.difference', ''),
            'goals' => data_get($setup, 'goals.objective', ''),
            'success_measure' => data_get($setup, 'goals.success_measure', 'Qualified enquiries from organic search'),
            'dependencies' => 'Website and Search Console access, accurate business information, and timely approval of proposed changes.',
            'keywords' => $website->seoTargetKeywords()->whereNull('archived_at')->orderBy('id')->get()
                ->map(fn (SeoTargetKeyword $keyword): array => [
                    'term' => $keyword->term, 'intended_url' => $keyword->intended_url,
                    'assignment_role' => $keyword->assignment_role ?? 'supporting',
                    'action' => '', 'target_best' => null, 'target_worst' => null, 'target_reason' => '',
                ])->all(),
        ];
    }

    /**
     * @param  array<string, mixed>  $brief
     * @return array<string, mixed>
     */
    private function baseline(Website $website, array $brief): array
    {
        $website->loadMissing('domains');
        $targets = $website->seoTargetKeywords()->whereNull('archived_at')
            ->with(['rankings' => fn ($query) => $query->where('website_id', $website->id)->where('device', 'desktop')
                ->where('location_code', config('services.dataforseo.location_code'))
                ->where('language_code', config('services.dataforseo.language_code'))
                ->whereIn('status', ['ranked', 'not_found'])->where('observed_at', '<=', now())
                ->latest('observed_at')->latest('id')->limit(40)])->get()->keyBy('normalized_term');
        $keywords = collect($brief['keywords'])->map(function (array $keyword) use ($targets): array {
            $keyword = array_replace(['intended_url' => null, 'action' => null, 'target_best' => null, 'target_worst' => null, 'target_reason' => null], $keyword);
            $target = $targets->get(SeoTargetKeyword::normalize($keyword['term']));
            $ranking = $target?->rankings->first();
            $recent = $ranking && $ranking->observed_at->gte(now()->subDays(30));

            return [
                ...$keyword,
                'action' => '',
                'baseline' => $recent ? [
                    'status' => $ranking->status, 'position' => $ranking->position, 'ranking_url' => $ranking->ranking_url,
                    'observed_at' => $ranking->observed_at->toIso8601String(), 'location_code' => $ranking->location_code,
                    'language_code' => $ranking->language_code, 'device' => $ranking->device,
                    'market_label' => $ranking->location_code === 2826 ? 'United Kingdom' : 'Selected search market',
                ] : null,
            ];
        })->all();
        $health = $website->healthReports()->where('status', 'completed')->latest('completed_at')->first();
        $findings = collect($health?->checks ?? [])->whereIn('status', ['failed', 'warning'])->take(8)
            ->map(fn (array $check): array => array_intersect_key($check, array_flip(['label', 'title', 'message', 'status'])))->values()->all();

        return [
            'website_name' => $website->name, 'domain' => $website->primaryDomain()?->domain,
            'generated_at' => now()->toIso8601String(),
            'summary' => '',
            'business' => array_intersect_key($brief, array_flip(['services', 'locations', 'audience', 'difference', 'goals'])),
            'success_measure' => $brief['success_measure'], 'dependencies' => $brief['dependencies'],
            'keywords' => $keywords,
            'health' => $health ? ['completed_at' => $health->completed_at?->toIso8601String(), 'passed' => $health->passed_checks,
                'warnings' => $health->warning_checks, 'failed' => $health->failed_checks, 'findings' => $findings] : null,
            'competitors' => $website->competitors()->where('excluded', false)->orderBy('domain')->limit(10)->pluck('domain')->all(),
            'roadmap' => [],
        ];
    }
}
