<?php

namespace App\Services;

use App\Jobs\DiscoverSeoProspects;
use App\Models\ProspectingIndustryProfile;
use App\Models\ProspectingLocation;
use App\Models\SeoProspectSearch;
use App\Models\User;
use Illuminate\Support\Str;

class AutomatedProspectDiscovery
{
    public function __construct(private SeoProspectCostEstimator $costEstimator) {}

    /** @return array{searches: int, operations: int, estimated_cost: float, enabled_industries: int, enabled_locations: int} */
    public function dispatch(User $owner, int $operationLimit): array
    {
        $operations = 0;
        $searches = 0;
        $estimatedCost = 0.0;
        $period = now()->format('Y-m');
        $locations = ProspectingLocation::query()->where('enabled', true)->orderByDesc('priority')->orderBy('name')->get();
        $profiles = ProspectingIndustryProfile::query()->where('enabled', true)->orderByDesc('priority')->orderBy('name')->get();

        foreach ($profiles as $profile) {
            foreach ($locations as $location) {
                $remainingOperations = $operationLimit - $operations;
                $serviceKeywords = collect($profile->search_keywords)->filter()->take(min(3, $remainingOperations))->values()->all();
                $keywordCount = count($serviceKeywords);

                if ($keywordCount === 0) {
                    continue;
                }

                $automationKey = hash('sha256', implode('|', [$profile->id, $location->id, $period]));

                $keywords = collect($serviceKeywords)->map(fn (string $keyword): string => Str::squish($keyword.' '.Str::lower($location->name)))->all();
                $search = SeoProspectSearch::query()->firstOrCreate(['automation_key' => $automationKey], [
                    'user_id' => $owner->id,
                    'prospecting_industry_profile_id' => $profile->id,
                    'prospecting_location_id' => $location->id,
                    'automated' => true,
                    'automation_key' => $automationKey,
                    'automatic_import_score' => $profile->automatic_import_score,
                    'industry' => $profile->name,
                    'location' => $location->name,
                    'service_keywords' => $serviceKeywords,
                    'keywords' => $keywords,
                    'minimum_position' => $profile->minimum_position,
                    'maximum_position' => $profile->maximum_position,
                    'maximum_pages' => $profile->maximum_site_size,
                    'estimated_api_cost' => $this->costEstimator->estimate($keywords, $profile->maximum_position),
                ]);

                if (! $search->wasRecentlyCreated) {
                    continue;
                }

                DiscoverSeoProspects::dispatch($search);
                $operations += $keywordCount;
                $searches++;
                $estimatedCost += (float) $search->estimated_api_cost;

                if ($operations >= $operationLimit) {
                    break 2;
                }
            }
        }

        return [
            'searches' => $searches,
            'operations' => $operations,
            'estimated_cost' => round($estimatedCost, 6),
            'enabled_industries' => $profiles->count(),
            'enabled_locations' => $locations->count(),
        ];
    }
}
