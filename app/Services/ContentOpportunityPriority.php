<?php

namespace App\Services;

use App\Models\SeoTargetKeyword;
use App\Models\Website;

class ContentOpportunityPriority
{
    /** @param array<string, mixed> $action
     * @return array{score: int, priority_reasons: list<string>}
     */
    public function score(Website $website, array $action): array
    {
        $score = (float) $action['score'];
        $reasons = [];
        $technicalBlocker = collect($action['evidence'])->flatMap(fn ($item): array => $item['checks'] ?? [])
            ->intersect(['page_available', 'indexable', 'website_reachable'])->isNotEmpty();
        if ($technicalBlocker) {
            return ['score' => 100, 'priority_reasons' => ['Verified audit evidence identifies an availability or indexing blocker.']];
        }
        $terms = array_map(SeoTargetKeyword::normalize(...), $action['queries']);
        $matched = $website->seoTargetKeywords->whereNull('archived_at')->filter(fn ($target): bool => in_array($target->normalized_term, $terms, true));
        if ($matched->isNotEmpty()) {
            $highPriority = $matched->contains('priority', 'high');
            $score += $highPriority ? 12 : 6;
            $reasons[] = $highPriority ? 'Matches a high-priority business target keyword.' : 'Matches a configured business target keyword.';
        }
        if (in_array('search', $action['sources'], true)) {
            $score += 5;
            $reasons[] = 'Supported by first-party Search Console observations.';
        }
        $intents = collect($action['evidence'])->map(fn ($item) => data_get($item, 'metrics.search_intent') ?? data_get($item, 'context.search_intent'));
        if ($intents->intersect(['commercial', 'transactional'])->isNotEmpty() && $matched->isNotEmpty()) {
            $score += 4;
            $reasons[] = 'Commercial intent aligns with a configured business goal.';
        }
        $date = collect($action['evidence'])->pluck('date')->filter()->sort()->last();
        if ($date && $date->lt(now()->subDays(30))) {
            $score -= 10;
            $reasons[] = 'Evidence is over 30 days old; refresh it before acting.';
        }
        $plan = $website->contentPlan;
        $research = $plan?->trend_research;
        if ($plan?->trend_research_enabled && ($research['status'] ?? null) === 'completed'
            && ($research['location_code'] ?? null) === (int) config('services.dataforseo.location_code')
            && ($research['language_code'] ?? null) === (string) config('services.dataforseo.language_code')
            && $plan->trend_researched_at?->gte(now()->subDays(14))) {
            $states = collect($research['terms'] ?? [])->only($terms)->pluck('state');
            if ($states->contains('rising')) {
                $score += 5;
                $reasons[] = 'Recent provider estimates show rising relative popularity for this topic; this is not search volume or a ranking forecast.';
            }
        }
        $mode = $website->contentPlan?->content_mode ?? 'balanced';
        $knownNew = ! $action['url'] && collect($action['evidence'])->contains(fn ($item): bool => in_array($item['source'] ?? null, ['competitor', 'discovery'], true) && empty(data_get($item, 'context.existing_page_url')));
        if (($mode === 'new_only' && $action['url']) || ($mode === 'existing_only' && $knownNew)) {
            $score -= 25;
            $reasons[] = 'Outside the current content mode; retained for planning rather than execution.';
        } elseif (($mode === 'new_only' && $knownNew) || ($mode === 'existing_only' && $action['url'])) {
            $score += 5;
            $reasons[] = 'Fits the website’s current content mode.';
        }

        return ['score' => (int) round(max(0, min(95, $score))), 'priority_reasons' => $reasons];
    }
}
