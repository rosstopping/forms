<?php

namespace App\Services;

use App\Models\SearchConsoleMetric;
use App\Models\Website;
use Illuminate\Support\Collection;

class GoogleAdsOpportunityFinder
{
    /** @return Collection<int, SearchConsoleMetric> */
    public function searchGaps(Website $website): Collection
    {
        $propertyUrl = $website->searchConsoleConnection?->property_url;
        if (! $propertyUrl) {
            return collect();
        }

        $query = SearchConsoleMetric::query()
            ->where('website_id', $website->id)
            ->where('property_hash', hash('sha256', $propertyUrl))
            ->whereNotNull('query')
            ->where('month', '>=', now()->subMonthsNoOverflow(3)->startOfMonth())
            ->where('impressions', '>=', 20)
            ->where('position', '>=', 8)
            ->where('ctr', '<=', 0.05);
        $latestMonth = (clone $query)->max('month');
        if (! $latestMonth) {
            return collect();
        }

        return $query->where('month', $latestMonth)
            ->orderByDesc('impressions')
            ->limit(8)
            ->get();
    }
}
