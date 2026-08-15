<?php

namespace App\Services;

use App\Models\SearchConsoleMetric;
use App\Models\Website;
use Illuminate\Support\Str;

class WebsiteAiContext
{
    public function for(Website $website): string
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
            'checks' => $report->checks,
            'metrics' => $report->metrics,
        ]);

        $searchConsole = SearchConsoleMetric::query()->whereBelongsTo($website)
            ->latest('month')->limit(100)->get(['month', 'dimension_key', 'query', 'clicks', 'impressions', 'ctr', 'position'])
            ->map(fn (SearchConsoleMetric $metric): array => [
                'source' => 'Google Search Console',
                'month' => $metric->month->toDateString(),
                'dimension' => $metric->dimension_key,
                'query' => $metric->query,
                'clicks' => $metric->clicks,
                'impressions' => $metric->impressions,
                'ctr' => $metric->ctr,
                'position' => $metric->position,
            ]);

        $snapshot = $website->seoSnapshots()->whereIn('status', ['completed', 'completed_with_errors'])->latest('snapshot_date')->first();
        $seo = $snapshot ? [
            'source' => 'SEO snapshot (third-party estimate)',
            'date' => $snapshot->snapshot_date?->toDateString(),
            'summary' => $snapshot->only(['organic_keywords', 'estimated_organic_traffic', 'top_3_keywords', 'top_10_keywords', 'top_20_keywords', 'top_100_keywords', 'backlinks', 'referring_domains', 'broken_backlinks', 'domain_rank']),
            'keywords' => $snapshot->keywords()->orderByDesc('estimated_traffic')->limit(50)->get(['keyword', 'position', 'previous_position', 'ranking_url', 'search_volume', 'estimated_traffic', 'keyword_difficulty', 'search_intent'])->toArray(),
            'opportunities' => $snapshot->opportunities()->whereIn('status', ['open', 'queued'])->orderByDesc('priority_score')->limit(20)->get(['title', 'summary', 'recommendation', 'metrics', 'priority_score'])->toArray(),
        ] : null;

        $searchOpportunities = $website->searchOpportunities()->whereIn('status', ['open', 'queued'])->orderByDesc('priority_score')->limit(20)->get([
            'query', 'page', 'title', 'summary', 'recommendation', 'metrics', 'priority_score', 'last_detected_at',
        ])->toArray();

        $context = (string) json_encode([
            'website' => ['id' => $website->id, 'name' => $website->name, 'domains' => $website->domains()->pluck('domain')->all()],
            'health_reports' => $healthReports,
            'search_console' => $searchConsole,
            'seo' => $seo,
            'search_opportunities' => $searchOpportunities,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);

        return Str::limit($context, 60000, PHP_EOL.'[Older or lower-priority context truncated.]');
    }
}
