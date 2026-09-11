<?php

namespace App\Services;

use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\Website;
use Illuminate\Support\Collection;

class SeoTargetKeywordPerformance
{
    /** @return Collection<int, array<string, mixed>> */
    public function latest(Website $website): Collection
    {
        return $this->targets($website)->map(fn (SeoTargetKeyword $target): array => $this->row($target, $this->successful($target)->take(2), $target->rankings->first()?->status === SeoTargetKeywordRanking::STATUS_FAILED));
    }

    /** @return Collection<int, array<string, mixed>> */
    public function monthly(Website $website): Collection
    {
        $latestMonth = today()->subMonthNoOverflow()->startOfMonth();
        $previousMonth = $latestMonth->copy()->subMonthNoOverflow();

        return $this->targets($website)->map(function (SeoTargetKeyword $target) use ($latestMonth, $previousMonth): array {
            $rankings = collect([
                $this->successful($target)->first(fn ($ranking) => $ranking->observed_at->betweenIncluded($latestMonth, $latestMonth->copy()->endOfMonth())),
                $this->successful($target)->first(fn ($ranking) => $ranking->observed_at->betweenIncluded($previousMonth, $previousMonth->copy()->endOfMonth())),
            ]);

            return $this->row($target, $rankings, false);
        });
    }

    /** @return Collection<int, SeoTargetKeyword> */
    private function targets(Website $website): Collection
    {
        return $website->seoTargetKeywords()->whereNull('archived_at')->with(['rankings' => fn ($query) => $query
            ->where('location_code', (int) config('services.dataforseo.location_code'))
            ->where('language_code', (string) config('services.dataforseo.language_code'))->where('device', 'desktop')
            ->latest('observed_at')->latest('id')])->orderByRaw("CASE WHEN priority = 'high' THEN 0 ELSE 1 END")->orderBy('term')->get();
    }

    private function successful(SeoTargetKeyword $target): Collection
    {
        return $target->rankings->whereIn('status', [SeoTargetKeywordRanking::STATUS_RANKED, SeoTargetKeywordRanking::STATUS_NOT_FOUND])->values();
    }

    /** @param Collection<int, SeoTargetKeywordRanking> $rankings
     * @return array<string, mixed>
     */
    private function row(SeoTargetKeyword $target, Collection $rankings, bool $latestFailed): array
    {
        $latest = $rankings->get(0);
        $previous = $rankings->get(1);
        $current = $latest?->position;
        $prior = $previous?->position;
        $movement = match (true) {
            ! $latest => 'awaiting_comparison',
            ! $previous => 'awaiting_comparison',
            $current !== null && $prior === null => 'newly_ranked',
            $current === null && $prior !== null => 'dropped',
            $current === $prior => 'unchanged',
            $current < $prior => 'improved',
            default => 'declined',
        };

        return ['target' => $target, 'latest' => $latest, 'previous' => $previous, 'movement' => $movement, 'change' => $current !== null && $prior !== null ? $prior - $current : null, 'latest_failed' => $latestFailed, 'source' => 'DataForSEO exact desktop rank check'];
    }
}
