<?php

namespace App\Services;

class SeoProspectCostEstimator
{
    /** @param array<int, string> $keywords */
    public function estimate(array $keywords, int $depth): float
    {
        $billedResultPages = (int) ceil(min(max($depth, 10), 100) / 10);

        return round(count($keywords) * $billedResultPages * (float) config('services.dataforseo.serp_live_cost_per_ten'), 6);
    }
}
