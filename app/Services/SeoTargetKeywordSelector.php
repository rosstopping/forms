<?php

namespace App\Services;

use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\Website;
use Illuminate\Support\Collection;

class SeoTargetKeywordSelector
{
    /** @return Collection<int, SeoTargetKeyword> */
    public function active(Website $website): Collection
    {
        return $website->seoTargetKeywords()->whereNull('archived_at')->with(['rankings' => fn ($query) => $query
            ->where('location_code', (int) config('services.dataforseo.location_code'))
            ->where('language_code', (string) config('services.dataforseo.language_code'))
            ->where('device', 'desktop')->latest('observed_at')->latest('id')])->get();
    }

    public function select(Collection $keywords): ?SeoTargetKeyword
    {
        return $keywords->sortBy(fn (SeoTargetKeyword $keyword): string => implode('|', [
            $keyword->priority === SeoTargetKeyword::PRIORITY_HIGH ? '0' : '1',
            $keyword->last_selected_at === null || $this->position($keyword) === null ? '0' : '1',
            str_pad((string) (101 - ($this->position($keyword) ?? 101)), 3, '0', STR_PAD_LEFT),
            $keyword->last_selected_at?->format('YmdHis') ?? '00000000000000',
            str_pad((string) $keyword->id, 12, '0', STR_PAD_LEFT),
        ]))->first();
    }

    /** @return array<int, array<string, mixed>> */
    public function snapshot(Collection $keywords): array
    {
        return $keywords->sortBy(fn (SeoTargetKeyword $keyword): string => ($keyword->priority === SeoTargetKeyword::PRIORITY_HIGH ? '0' : '1').$keyword->term)
            ->map(function (SeoTargetKeyword $keyword): array {
                $ranking = $keyword->rankings->first(fn ($item) => in_array($item->status, [SeoTargetKeywordRanking::STATUS_RANKED, SeoTargetKeywordRanking::STATUS_NOT_FOUND], true));

                return [
                    'id' => $keyword->id, 'term' => $keyword->term, 'priority' => $keyword->priority, 'note' => $keyword->note,
                    'position' => $ranking?->position, 'ranking_url' => $ranking?->ranking_url,
                    'status' => $ranking?->status ?? 'awaiting_first_check', 'observed_at' => $ranking?->observed_at?->toIso8601String(),
                    'source' => 'dataforseo_exact_serp', 'location_code' => (int) config('services.dataforseo.location_code'),
                    'language_code' => (string) config('services.dataforseo.language_code'), 'device' => 'desktop',
                ];
            })->values()->all();
    }

    private function position(SeoTargetKeyword $keyword): ?int
    {
        return $keyword->rankings->first(fn ($ranking) => in_array($ranking->status, [SeoTargetKeywordRanking::STATUS_RANKED, SeoTargetKeywordRanking::STATUS_NOT_FOUND], true))?->position;
    }
}
