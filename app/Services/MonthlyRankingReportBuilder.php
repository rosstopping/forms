<?php

namespace App\Services;

use App\Models\SearchConsoleMetric;
use App\Models\SeoSnapshot;
use App\Models\Website;
use Illuminate\Support\Collection;

class MonthlyRankingReportBuilder
{
    /** @return array<string, mixed> */
    public function build(Website $website): array
    {
        $propertyUrl = $website->searchConsoleConnection?->property_url;
        $searchMetrics = filled($propertyUrl)
            ? SearchConsoleMetric::query()->where('website_id', $website->id)
                ->where('property_hash', hash('sha256', $propertyUrl))
                ->where('month', '<', today()->startOfMonth())->latest('month')->get()
            : collect();
        $siteMetrics = $searchMetrics->where('dimension_key', SearchConsoleMetric::SITE_DIMENSION_KEY)
            ->unique(fn (SearchConsoleMetric $metric): string => $metric->month->toDateString())->take(2)->values();
        $latestSearch = $siteMetrics->get(0);
        $previousSearch = $siteMetrics->get(1);
        $queryMovements = $latestSearch && $previousSearch
            ? $this->queryMovements($searchMetrics, $latestSearch->month->toDateString(), $previousSearch->month->toDateString())
            : collect();

        $seo = $website->seoSnapshots()
            ->whereIn('status', [SeoSnapshot::STATUS_COMPLETED, SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS])
            ->where('snapshot_date', '<', today()->startOfMonth())->latest('snapshot_date')->latest('completed_at')->get()
            ->reject(fn (SeoSnapshot $snapshot): bool => data_get($snapshot->metadata, 'historical') === true)
            ->unique(fn (SeoSnapshot $snapshot): string => $snapshot->snapshot_date->toDateString())->take(2)->values();

        $highlights = collect();
        $this->addChange($highlights, 'Google clicks', $latestSearch?->clicks, $previousSearch?->clicks);
        $this->addChange($highlights, 'Search impressions', $latestSearch?->impressions, $previousSearch?->impressions);
        $this->addChange($highlights, 'Average Google position', $latestSearch?->position, $previousSearch?->position, true);
        $this->addChange($highlights, 'Estimated organic traffic', $seo->get(0)?->estimated_organic_traffic, $seo->get(1)?->estimated_organic_traffic, false, true);
        $this->addChange($highlights, 'Ranking keywords', $seo->get(0)?->organic_keywords, $seo->get(1)?->organic_keywords);
        $this->addChange($highlights, 'Top-10 keywords', $seo->get(0)?->top_10_keywords, $seo->get(1)?->top_10_keywords);

        return [
            'latestSearch' => $latestSearch, 'previousSearch' => $previousSearch,
            'latestSeo' => $seo->get(0), 'previousSeo' => $seo->get(1), 'highlights' => $highlights,
            'queryWins' => $queryMovements->where('direction', 'improved')->take(5)->values(),
            'queryDeclines' => $queryMovements->where('direction', 'declined')->take(3)->values(),
            'targetKeywords' => app(SeoTargetKeywordPerformance::class)->monthly($website),
        ];
    }

    /** @param Collection<int, SearchConsoleMetric> $metrics
     * @return Collection<int, array{query: string, position: float, position_change: float, clicks: float, click_change: float, direction: string}>
     */
    protected function queryMovements(Collection $metrics, string $latestMonth, string $previousMonth): Collection
    {
        $latest = $metrics->filter(fn (SearchConsoleMetric $metric): bool => $metric->query !== null && $metric->month->toDateString() === $latestMonth);
        $previous = $metrics->filter(fn (SearchConsoleMetric $metric): bool => $metric->query !== null && $metric->month->toDateString() === $previousMonth)->keyBy('dimension_key');

        return $latest->map(function (SearchConsoleMetric $metric) use ($previous): ?array {
            $prior = $previous->get($metric->dimension_key);
            if (! $prior) {
                return null;
            }
            $positionChange = $prior->position - $metric->position;
            $clickChange = $metric->clicks - $prior->clicks;
            if (abs($positionChange) < 0.1 && abs($clickChange) < 0.01) {
                return null;
            }
            $improved = $positionChange > 0 || (abs($positionChange) < 0.1 && $clickChange > 0);

            return ['query' => (string) $metric->query, 'position' => $metric->position,
                'position_change' => $positionChange, 'clicks' => $metric->clicks,
                'click_change' => $clickChange, 'direction' => $improved ? 'improved' : 'declined'];
        })->filter()->sortByDesc(fn (array $movement): float => abs($movement['position_change']) + abs($movement['click_change']))->values();
    }

    /** @param Collection<int, array{label: string, change: string, direction: string}> $highlights */
    protected function addChange(Collection $highlights, string $label, mixed $latest, mixed $previous, bool $lowerIsBetter = false, bool $approximate = false): void
    {
        if (! is_numeric($latest) || ! is_numeric($previous)) {
            return;
        }
        $change = (float) $latest - (float) $previous;
        if (abs($change) < 0.01) {
            return;
        }
        $improved = $lowerIsBetter ? $change < 0 : $change > 0;
        $highlights->push(['label' => $label,
            'change' => ($change > 0 ? '+' : '').($approximate ? '~' : '').number_format($change, abs($change) < 10 ? 1 : 0),
            'direction' => $improved ? 'improved' : 'declined']);
    }
}
