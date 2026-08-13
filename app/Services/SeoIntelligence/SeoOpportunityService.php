<?php

namespace App\Services\SeoIntelligence;

use App\Models\SeoKeyword;
use App\Models\SeoOpportunity;
use App\Models\SeoSnapshot;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SeoOpportunityService
{
    /**
     * @param  Collection<int, SeoKeyword>  $currentKeywords
     * @param  Collection<int, SeoKeyword>|null  $previousKeywords
     * @return array<int, array<string, mixed>>
     */
    public function find(Collection $currentKeywords, ?Collection $previousKeywords = null): array
    {
        $previousByKeyword = ($previousKeywords ?? collect())
            ->keyBy(fn (SeoKeyword $keyword): string => $this->keywordKey($keyword));
        $opportunities = collect();

        foreach ($currentKeywords as $keyword) {
            $previous = $previousByKeyword->get($this->keywordKey($keyword));
            $position = $keyword->position;
            $volume = $keyword->search_volume ?? 0;

            if ($position >= 4 && $position <= 20) {
                $opportunities->push($this->opportunity(
                    SeoOpportunity::TYPE_STRIKING_DISTANCE,
                    $keyword,
                    'Move “'.$keyword->keyword.'” towards page one',
                    sprintf('The domain ranks at position %d for an estimated %s monthly searches.', $position, number_format($volume)),
                    'Review the ranking page against the search intent. Strengthen its title, main heading, relevant supporting copy, and internal links without changing claims that already perform well.',
                    $this->volumeScore($volume) + ((21 - $position) / 17 * 40) + $this->intentBonus($keyword),
                    $previous,
                ));
            }

            if ($volume >= $this->highVolumeMinimum() && $position >= 11 && $position <= 50) {
                $opportunities->push($this->opportunity(
                    SeoOpportunity::TYPE_HIGH_VOLUME,
                    $keyword,
                    'Improve visibility for high-volume search “'.$keyword->keyword.'”',
                    sprintf('This keyword has an estimated volume of %s and currently ranks at position %d.', number_format($volume), $position),
                    'Compare the ranking page with the current top results, identify useful topics or proof that are genuinely missing, and add relevant internal links from established pages.',
                    $this->volumeScore($volume) + min(40, (($position - 10) / 40) * 40),
                    $previous,
                ));
            }

            if (in_array($keyword->search_intent, ['commercial', 'transactional'], true) && $volume >= $this->commercialVolumeMinimum() && $position >= 4 && $position <= 30) {
                $opportunities->push($this->opportunity(
                    SeoOpportunity::TYPE_COMMERCIAL,
                    $keyword,
                    'Strengthen the commercial page for “'.$keyword->keyword.'”',
                    sprintf('This %s-intent keyword ranks at position %d with an estimated volume of %s.', $keyword->search_intent, $position, number_format($volume)),
                    'Make the ranking page useful for a buying decision: clarify the offer, demonstrate relevant proof, answer material objections, and provide an accurate next step or call to action.',
                    $this->volumeScore($volume) + 20 + min(30, ((31 - $position) / 27) * 30),
                    $previous,
                ));
            }

            if ($previous) {
                $change = $position - $previous->position;

                if ($change >= $this->movementMinimum()) {
                    $opportunities->push($this->opportunity(
                        SeoOpportunity::TYPE_DECLINING,
                        $keyword,
                        'Recover declining keyword “'.$keyword->keyword.'”',
                        sprintf('The locally observed ranking fell from position %d to %d.', $previous->position, $position),
                        'Check whether the ranking page, search intent, internal links, or competing results changed between snapshots. Restore useful lost coverage and resolve technical issues before making broad rewrites.',
                        $this->volumeScore($volume) + min(50, $change * 10),
                        $previous,
                    ));
                } elseif ($change <= -$this->movementMinimum()) {
                    $opportunities->push($this->opportunity(
                        SeoOpportunity::TYPE_IMPROVING,
                        $keyword,
                        'Protect gains for “'.$keyword->keyword.'”',
                        sprintf('The locally observed ranking improved from position %d to %d.', $previous->position, $position),
                        'Preserve the page elements that contributed to this improvement. Reinforce relevant internal links and avoid unnecessary rewrites while the ranking is moving positively.',
                        $this->volumeScore($volume) + min(50, abs($change) * 8),
                        $previous,
                    ));
                }
            }
        }

        return $opportunities
            ->groupBy('type')
            ->flatMap(fn (Collection $items): Collection => $items->sortByDesc('priority_score')->take($this->perTypeLimit()))
            ->sortByDesc('priority_score')
            ->take($this->maximumResults())
            ->values()
            ->all();
    }

    public function generate(SeoSnapshot $snapshot): Collection
    {
        $snapshot->loadMissing('keywords');
        $previousSnapshot = SeoSnapshot::query()
            ->where('website_id', $snapshot->website_id)
            ->where('provider', $snapshot->provider)
            ->where('domain', $snapshot->domain)
            ->where('location_code', $snapshot->location_code)
            ->where('language_code', $snapshot->language_code)
            ->whereIn('status', [SeoSnapshot::STATUS_COMPLETED, SeoSnapshot::STATUS_COMPLETED_WITH_ERRORS])
            ->where('id', '<', $snapshot->id)
            ->latest('completed_at')
            ->first();
        $previousKeywords = $previousSnapshot?->keywords()->get() ?? collect();

        $opportunities = $this->find($snapshot->keywords, $previousKeywords);
        $snapshot->opportunities()->createMany(array_map(
            fn (array $opportunity): array => ['website_id' => $snapshot->website_id, ...$opportunity],
            $opportunities,
        ));

        return $snapshot->opportunities()->with('keyword')->orderByDesc('priority_score')->get();
    }

    /** @return array<string, mixed> */
    protected function opportunity(string $type, SeoKeyword $keyword, string $title, string $summary, string $recommendation, float $priorityScore, ?SeoKeyword $previous): array
    {
        return [
            'seo_keyword_id' => $keyword->id,
            'fingerprint' => hash('sha256', $type.'|'.$keyword->fingerprint),
            'type' => $type,
            'status' => SeoOpportunity::STATUS_OPEN,
            'title' => $title,
            'summary' => $summary,
            'recommendation' => $recommendation,
            'metrics' => [
                'data_source' => 'dataforseo_estimate',
                'keyword' => $keyword->keyword,
                'ranking_url' => $keyword->ranking_url,
                'position' => $keyword->position,
                'previous_snapshot_position' => $previous?->position,
                'position_change' => $previous ? $previous->position - $keyword->position : null,
                'search_volume' => $keyword->search_volume,
                'estimated_traffic' => $keyword->estimated_traffic,
                'cpc' => $keyword->cpc,
                'search_intent' => $keyword->search_intent,
            ],
            'priority_score' => round(min(100, max(0, $priorityScore)), 4),
        ];
    }

    protected function keywordKey(SeoKeyword $keyword): string
    {
        return Str::lower(trim($keyword->keyword));
    }

    protected function volumeScore(int $volume): float
    {
        return min(50, log10($volume + 1) * 15);
    }

    protected function intentBonus(SeoKeyword $keyword): int
    {
        return in_array($keyword->search_intent, ['commercial', 'transactional'], true) ? 10 : 0;
    }

    protected function highVolumeMinimum(): int
    {
        return (int) config('services.dataforseo.opportunities.high_volume_minimum', 100);
    }

    protected function commercialVolumeMinimum(): int
    {
        return (int) config('services.dataforseo.opportunities.commercial_volume_minimum', 20);
    }

    protected function movementMinimum(): int
    {
        return (int) config('services.dataforseo.opportunities.movement_minimum', 3);
    }

    protected function perTypeLimit(): int
    {
        return (int) config('services.dataforseo.opportunities.per_type_limit', 10);
    }

    protected function maximumResults(): int
    {
        return (int) config('services.dataforseo.opportunities.maximum_results', 50);
    }
}
