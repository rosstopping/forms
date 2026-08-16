<?php

namespace App\Services;

use App\Models\SearchConsoleMetric;
use App\Models\SeoKeyword;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class WebsiteAiContext
{
    public function for(Website $website, ?string $question = null): string
    {
        $healthReports = $website->healthReports()->latest('completed_at')->limit(3)->get([
            'overall_status', 'passed_checks', 'warning_checks', 'failed_checks', 'categories', 'checks', 'metrics', 'completed_at',
        ])->map(fn ($report): array => [
            'source' => 'Website health report',
            'date' => $report->completed_at?->toDateString(),
            'status' => $report->overall_status,
            'passed' => $report->passed_checks,
            'warnings' => $report->warning_checks,
            'failed' => $report->failed_checks,
            'categories' => $report->categories,
            'notable_checks' => collect($report->checks)->whereIn('status', ['warning', 'failed'])->take(30)->values()->all(),
            'metrics' => collect($report->metrics)->take(30)->all(),
        ]);

        $searchConsoleMetrics = SearchConsoleMetric::query()->whereBelongsTo($website)
            ->where('month', '>=', now()->subMonths(23)->startOfMonth())
            ->latest('month')->limit(25000)->get(['month', 'dimension_key', 'query', 'clicks', 'impressions', 'ctr', 'position']);
        $searchConsoleSiteHistory = $searchConsoleMetrics
            ->where('dimension_key', SearchConsoleMetric::SITE_DIMENSION_KEY)
            ->sortBy('month')
            ->map(fn (SearchConsoleMetric $metric): array => [
                'source' => 'Google Search Console',
                'month' => $metric->month->toDateString(),
                'clicks' => $metric->clicks,
                'impressions' => $metric->impressions,
                'ctr' => $metric->ctr,
                'position' => $metric->position,
            ])->values();
        $searchConsoleKeywordMovements = $this->searchConsoleKeywordMovements($searchConsoleMetrics, $question);
        $searchConsoleRecentQueries = $searchConsoleMetrics->whereNotNull('query')
            ->unique('query')->take(50)
            ->map(fn (SearchConsoleMetric $metric): array => [
                'source' => 'Google Search Console',
                'month' => $metric->month->toDateString(),
                'query' => $metric->query,
                'clicks' => $metric->clicks,
                'impressions' => $metric->impressions,
                'ctr' => $metric->ctr,
                'position' => $metric->position,
            ])->values();

        $snapshot = $website->seoSnapshots()->whereIn('status', ['completed', 'completed_with_errors'])->latest('snapshot_date')->first();
        $seoSnapshotHistory = $website->seoSnapshots()->whereIn('status', ['completed', 'completed_with_errors'])
            ->latest('snapshot_date')->limit(24)->get([
                'snapshot_date', 'organic_keywords', 'estimated_organic_traffic', 'top_3_keywords', 'top_10_keywords',
                'top_20_keywords', 'top_100_keywords', 'backlinks', 'referring_domains', 'domain_rank',
            ])->sortBy('snapshot_date')->values()->toArray();
        $seo = $snapshot ? [
            'source' => 'SEO snapshot (third-party estimate)',
            'date' => $snapshot->snapshot_date?->toDateString(),
            'summary' => $snapshot->only(['organic_keywords', 'estimated_organic_traffic', 'top_3_keywords', 'top_10_keywords', 'top_20_keywords', 'top_100_keywords', 'backlinks', 'referring_domains', 'broken_backlinks', 'domain_rank']),
            'top_keywords' => $snapshot->keywords()->orderByDesc('estimated_traffic')->limit(30)->get(['keyword', 'position', 'previous_position', 'ranking_url', 'search_volume', 'estimated_traffic', 'keyword_difficulty', 'search_intent'])->toArray(),
            'keyword_movements' => $snapshot->keywords()->whereNotNull('previous_position')
                ->orderByRaw('ABS(previous_position - position) DESC')->limit(40)
                ->get(['keyword', 'position', 'previous_position', 'ranking_url', 'search_volume', 'estimated_traffic'])
                ->map(fn (SeoKeyword $keyword): array => [
                    ...$keyword->only(['keyword', 'position', 'previous_position', 'ranking_url', 'search_volume', 'estimated_traffic']),
                    'position_change' => $keyword->previous_position - $keyword->position,
                    'direction' => $keyword->position < $keyword->previous_position ? 'improved' : ($keyword->position > $keyword->previous_position ? 'declined' : 'unchanged'),
                    'comparison_note' => 'Provider-reported previous position; this is not necessarily a comparison with the earliest Search Console month.',
                ])->toArray(),
            'opportunities' => $snapshot->opportunities()->whereIn('status', ['open', 'queued'])->orderByDesc('priority_score')->limit(10)->get(['title', 'summary', 'recommendation', 'metrics', 'priority_score'])->toArray(),
        ] : null;

        $searchOpportunities = $website->searchOpportunities()->whereIn('status', ['open', 'queued'])->orderByDesc('priority_score')->limit(20)->get([
            'query', 'page', 'title', 'summary', 'recommendation', 'metrics', 'priority_score', 'last_detected_at',
        ])->take(10)->toArray();

        $queryMetrics = $searchConsoleMetrics->whereNotNull('query');

        $contextData = [
            'website' => ['id' => $website->id, 'name' => $website->name, 'domains' => $website->domains()->pluck('domain')->all()],
            'data_coverage' => [
                'search_console_first_month_in_context' => $searchConsoleMetrics->min('month')?->toDateString(),
                'search_console_latest_month_in_context' => $searchConsoleMetrics->max('month')?->toDateString(),
                'search_console_distinct_queries_in_context' => $queryMetrics->pluck('query')->unique()->count(),
                'search_console_keyword_comparison_note' => 'Only queries stored in both the selected comparison month and latest available month can be compared. The monthly sample contains at most 1,000 of Search Console’s highest-click queries, and Search Console may omit anonymised queries. Site-average improvement does not prove every keyword improved.',
                'seo_keyword_comparison_note' => 'SEO keyword previous_position values are provider-reported comparisons. Do not describe them as changes since a user-specified date unless that date is explicitly present.',
            ],
            'search_console_site_history' => $searchConsoleSiteHistory,
            'search_console_keyword_movements' => $searchConsoleKeywordMovements,
            'search_console_recent_queries' => $searchConsoleRecentQueries,
            'seo_snapshot_history' => $seoSnapshotHistory,
            'seo' => $seo,
            'health_reports' => $healthReports,
            'search_opportunities' => $searchOpportunities,
        ];

        if ($question !== null && $this->isSearchPerformanceQuestion($question)) {
            $contextData['health_reports'] = [];

            if ($this->isFirstPartySearchQuestion($question)) {
                $contextData['seo_snapshot_history'] = [];
                $contextData['seo'] = null;
            }

            if (! Str::contains(Str::lower($question), ['opportunit', 'recommend', 'priorit'])) {
                $contextData['search_opportunities'] = [];

                if (is_array($contextData['seo'])) {
                    $contextData['seo']['opportunities'] = [];
                    $contextData['seo']['top_keywords'] = array_slice($contextData['seo']['top_keywords'], 0, 10);
                }
            }
        }

        $context = $this->encode($contextData);

        if (strlen($context) > 60000) {
            $contextData['health_reports'] = $healthReports->map(fn (array $report): array => collect($report)->except(['notable_checks', 'metrics'])->all());
            $contextData['search_opportunities'] = array_slice($searchOpportunities, 0, 5);

            if (is_array($contextData['seo'])) {
                $contextData['seo']['top_keywords'] = array_slice($contextData['seo']['top_keywords'], 0, 10);
                $contextData['seo']['opportunities'] = array_slice($contextData['seo']['opportunities'], 0, 5);
            }

            $context = $this->encode($contextData);
        }

        if (strlen($context) > 60000) {
            $contextData['health_reports'] = [];
            $contextData['search_opportunities'] = [];

            if (is_array($contextData['seo'])) {
                $contextData['seo']['top_keywords'] = [];
                $contextData['seo']['opportunities'] = [];
            }

            $context = $this->encode($contextData);
        }

        return $context;
    }

    /** @param array<string, mixed> $context */
    protected function encode(array $context): string
    {
        return (string) json_encode($context, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    protected function isSearchPerformanceQuestion(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'keyword', 'position', 'ranking', 'ranked', 'search console', 'click', 'impression', 'ctr', 'organic traffic',
        ]);
    }

    protected function isFirstPartySearchQuestion(string $question): bool
    {
        return Str::contains(Str::lower($question), [
            'average position', 'search console', 'click', 'impression', 'ctr',
        ]);
    }

    /**
     * @param  Collection<int, SearchConsoleMetric>  $metrics
     * @return array<int, array<string, mixed>>
     */
    protected function searchConsoleKeywordMovements(Collection $metrics, ?string $question): array
    {
        $queryMetrics = $metrics->whereNotNull('query');
        $comparisonMonth = $this->comparisonMonth($question) ?? $queryMetrics->min('month');
        $latestMonth = $queryMetrics->max('month');

        if (! $comparisonMonth || ! $latestMonth || $comparisonMonth->equalTo($latestMonth)) {
            return ['comparison_month' => $comparisonMonth?->toDateString(), 'latest_month' => $latestMonth?->toDateString(), 'comparable_query_count' => 0, 'improved' => [], 'declined' => [], 'unchanged' => []];
        }

        $movements = $queryMetrics
            ->groupBy('query')
            ->map(function (Collection $queryMetrics, string $query) use ($comparisonMonth, $latestMonth): ?array {
                $first = $queryMetrics->first(fn (SearchConsoleMetric $metric): bool => $metric->month->equalTo($comparisonMonth));
                $latest = $queryMetrics->first(fn (SearchConsoleMetric $metric): bool => $metric->month->equalTo($latestMonth));

                if (! $first || ! $latest) {
                    return null;
                }

                $positionChange = $first->position - $latest->position;

                return [
                    'source' => 'Google Search Console',
                    'query' => $query,
                    'first_month' => $first->month->toDateString(),
                    'first_position' => $first->position,
                    'latest_month' => $latest->month->toDateString(),
                    'latest_position' => $latest->position,
                    'position_change' => round($positionChange, 2),
                    'direction' => $positionChange > 0 ? 'improved' : ($positionChange < 0 ? 'declined' : 'unchanged'),
                    'latest_clicks' => $latest->clicks,
                    'latest_impressions' => $latest->impressions,
                ];
            })
            ->filter()
            ->values();

        return [
            'comparison_month' => $comparisonMonth->toDateString(),
            'latest_month' => $latestMonth->toDateString(),
            'comparable_query_count' => $movements->count(),
            'improved' => $movements->where('direction', 'improved')->sortByDesc('position_change')->take(30)->values()->all(),
            'declined' => $movements->where('direction', 'declined')->sortBy('position_change')->take(20)->values()->all(),
            'unchanged' => $movements->where('direction', 'unchanged')->take(10)->values()->all(),
        ];
    }

    protected function comparisonMonth(?string $question): ?Carbon
    {
        if ($question === null || ! preg_match('/\b(january|february|march|april|may|june|july|august|september|october|november|december|jan|feb|mar|apr|jun|jul|aug|sep|sept|oct|nov|dec)\s+(20\d{2})\b/i', $question, $matches)) {
            return null;
        }

        return Carbon::createFromFormat('!M Y', substr($matches[1], 0, 3).' '.$matches[2])->startOfMonth();
    }
}
