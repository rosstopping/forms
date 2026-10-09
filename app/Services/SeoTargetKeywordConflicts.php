<?php

namespace App\Services;

use App\Models\SeoTargetKeyword;
use App\Models\Website;
use Illuminate\Support\Collection;

class SeoTargetKeywordConflicts
{
    /** @param Collection<int, SeoTargetKeyword> $targets
     * @return array<int, array<string, mixed>>
     */
    public function forTargets(Website $website, Collection $targets): array
    {
        (new \Illuminate\Database\Eloquent\Collection($targets->all()))->loadMissing(['rankings' => fn ($query) => $query
            ->where('location_code', (int) config('services.dataforseo.location_code'))
            ->where('language_code', (string) config('services.dataforseo.language_code'))->where('device', 'desktop')
            ->latest('observed_at')->latest('id')]);
        $targets->each(fn (SeoTargetKeyword $target) => $target->setRelation('website', $website));
        $property = $website->searchConsoleConnection?->property_url;
        $saved = $property ? $website->contentPlan?->generations()->where('search_performance_property', $property)
            ->where('started_at', '>=', now()->subDays(28))->whereNotNull('search_performance')->latest('started_at')
            ->first(['search_performance', 'started_at']) : null;

        return $targets->mapWithKeys(fn (SeoTargetKeyword $target): array => [$target->id => [
            ...$this->assess($target, $saved?->search_performance ?? []),
            'sample_at' => $saved?->started_at?->toIso8601String(),
        ]])->all();
    }

    /** @param array<int, array<string, mixed>> $rows
     * @return array{state: string, message: string, pages: list<array<string, mixed>>}
     */
    public function assess(SeoTargetKeyword $target, array $rows): array
    {
        if (! $target->intended_url) {
            return ['state' => 'unassigned', 'message' => 'Assign an intended page before assessing destination conflicts.', 'pages' => []];
        }
        $tracker = app(SeoImpactTracker::class);
        $intended = $tracker->urlKey($target->intended_url);
        $ranking = $target->rankings->first(fn ($row): bool => in_array($row->status, ['ranked', 'not_found'], true));
        $pages = collect($rows)->filter(fn ($row): bool => is_string($row['query'] ?? null) && SeoTargetKeyword::normalize($row['query']) === $target->normalized_term
            && is_string($row['page'] ?? null) && $tracker->websiteUrls($target->website, [$row['page']]) !== [])
            ->groupBy(fn ($row): string => $tracker->urlKey($row['page']))->map(function (Collection $group, string $key) use ($intended): array {
                $impressions = $group->sum('impressions');

                return ['url' => $group->first()['page'], 'intended' => $key === $intended, 'clicks' => $group->sum('clicks'), 'impressions' => $impressions,
                    'position' => $impressions > 0 ? $group->sum(fn ($row): float => (float) ($row['position'] ?? 0) * (float) ($row['impressions'] ?? 0)) / $impressions : null];
            })->sortByDesc('impressions')->values();
        $otherVisible = $pages->contains(fn ($page): bool => ! $page['intended'] && $page['impressions'] >= 100);
        $rankDiffers = $ranking?->status === 'ranked' && $ranking->observed_at?->gte(now()->subDays(30)) && $ranking->ranking_url && $tracker->urlKey($ranking->ranking_url) !== $intended;
        if ($rankDiffers || $otherVisible) {
            $intent = $target->search_intent
                ? 'Check whether the other page serves the same '.$target->search_intent.' intent and whether it is taking useful traffic from the intended destination.'
                : 'Set the intended search intent, then check whether the pages serve the same user need.';
            $message = 'Destination review suggested. '.$intent.' Multiple visible pages alone do not establish cannibalisation.';
            if ($pages->isEmpty()) {
                $message .= ' No recent matching Search Console sample is available; the desktop rank observation alone is insufficient.';
            }

            return ['state' => 'review', 'message' => $message, 'pages' => $pages->all()];
        }

        return ['state' => $pages->count() > 1 ? 'multiple_pages' : 'no_conflict_observed',
            'message' => $pages->count() > 1 ? 'Several pages appear in the sample, with no substantial destination conflict flagged. Different intents can legitimately rank together.' : 'No destination conflict observed in the available sample. Missing rows are not proof of absence.',
            'pages' => $pages->all()];
    }
}
