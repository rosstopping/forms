<?php

namespace App\Services;

use App\Models\SearchConsoleMetric;
use App\Models\SeoSnapshot;
use App\Models\Website;
use Illuminate\Support\Collection;

class RankingReportBuilder
{
    /** @return array<string, mixed> */
    public function build(Website $website): array
    {
        $seo = $website->seoSnapshots()
            ->whereIn('status', [SeoSnapshot::STATUS_COMPLETED, SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS])
            ->latest('snapshot_date')
            ->latest('completed_at')
            ->get()
            ->reject(fn (SeoSnapshot $snapshot): bool => data_get($snapshot->metadata, 'historical') === true)
            ->unique(fn (SeoSnapshot $snapshot): string => $snapshot->snapshot_date->toDateString())
            ->take(2)
            ->values();
        $propertyUrl = $website->searchConsoleConnection?->property_url;
        $search = filled($propertyUrl)
            ? SearchConsoleMetric::query()
                ->where('website_id', $website->id)
                ->where('property_hash', hash('sha256', $propertyUrl))
                ->where('dimension_key', SearchConsoleMetric::SITE_DIMENSION_KEY)
                ->latest('month')
                ->limit(2)
                ->get()
            : collect();
        $latestSeo = $seo->get(0);
        $previousSeo = $seo->get(1);
        $latestSearch = $search->get(0);
        $previousSearch = $search->get(1);
        $searchPeriodIsComplete = $latestSearch?->month->isBefore(today()->startOfMonth()) === true;
        $highlights = collect();

        $this->addChange($highlights, 'Estimated organic traffic', $latestSeo?->estimated_organic_traffic, $previousSeo?->estimated_organic_traffic, false, true);
        $this->addChange($highlights, 'Ranking keywords', $latestSeo?->organic_keywords, $previousSeo?->organic_keywords);
        $this->addChange($highlights, 'Top-10 keywords', $latestSeo?->top_10_keywords, $previousSeo?->top_10_keywords);
        if ($searchPeriodIsComplete) {
            $this->addChange($highlights, 'Google clicks', $latestSearch?->clicks, $previousSearch?->clicks);
            $this->addChange($highlights, 'Search impressions', $latestSearch?->impressions, $previousSearch?->impressions);
            $this->addChange($highlights, 'Average position', $latestSearch?->position, $previousSearch?->position, true);
        }

        $opportunities = $website->seoOpportunities()
            ->where('status', 'open')
            ->orderByDesc('priority_score')
            ->limit(3)
            ->get(['title', 'summary', 'priority_score']);

        return compact('latestSeo', 'previousSeo', 'latestSearch', 'previousSearch', 'highlights', 'opportunities');
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
        $highlights->push([
            'label' => $label,
            'change' => ($change > 0 ? '+' : '').($approximate ? '~' : '').number_format($change, abs($change) < 10 ? 1 : 0),
            'direction' => $improved ? 'improved' : 'declined',
        ]);
    }
}
