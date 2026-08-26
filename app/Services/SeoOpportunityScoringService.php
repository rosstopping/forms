<?php

namespace App\Services;

use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectRanking;
use Illuminate\Support\Collection;

class SeoOpportunityScoringService
{
    /** @return array<string, mixed> */
    public function score(SeoProspectCandidate $candidate): array
    {
        if ($candidate->qualification_status !== 'suitable') {
            return [
                'opportunity_score' => null,
                'score_breakdown' => null,
            ];
        }

        $candidate->loadMissing(['rankings', 'search.industryProfile']);
        $ranking = $this->rankingScore($candidate);
        $audit = $this->auditScore($candidate);
        $siteFit = $this->siteFitScore($candidate);
        $migration = $this->migrationScore($candidate);
        $breakdown = compact('ranking', 'audit', 'siteFit', 'migration');

        return [
            'opportunity_score' => collect($breakdown)->sum('score'),
            'score_breakdown' => $breakdown,
            ...$this->commercialScore($candidate),
            'observations' => [
                ...($candidate->observations ?? []),
                'outreach' => $this->outreachObservations($candidate),
            ],
        ];
    }

    /** @return array{commercial_opportunity_score: int|null, commercial_score_breakdown: array<string, mixed>|null} */
    private function commercialScore(SeoProspectCandidate $candidate): array
    {
        $profile = $candidate->search->industryProfile;

        if (! $profile) {
            return ['commercial_opportunity_score' => null, 'commercial_score_breakdown' => null];
        }

        $bestPosition = $this->commercialOpportunityRanking($candidate)?->position ?? 0;
        $ranking = match (true) {
            $bestPosition >= 8 && $bestPosition <= 30 => 40,
            $bestPosition >= 31 && $bestPosition <= 50 => 25,
            $bestPosition >= 1 && $bestPosition <= 7 => 8,
            default => 0,
        };
        $customerValue = match ($profile->customer_value_band) {
            'very_high' => 25,
            'high' => 20,
            'medium' => 12,
            default => 6,
        };
        $siteManageability = match (true) {
            $candidate->page_count <= 0 => 0,
            $candidate->page_count <= 10 => 20,
            $candidate->page_count <= 20 => 15,
            $candidate->page_count <= 30 => 10,
            default => max(0, 10 - (int) ceil(($candidate->page_count - 30) / 5)),
        };
        $commercialIntent = $candidate->rankings->contains(fn (SeoProspectRanking $ranking): bool => collect($profile->search_keywords)->contains(fn (string $keyword): bool => str($ranking->keyword)->lower()->startsWith((string) str($keyword)->lower()))) ? 15 : 8;
        $breakdown = [
            'ranking_opportunity' => ['score' => $ranking, 'maximum' => 40, 'explanation' => $bestPosition > 0 ? "Best stored organic position: {$bestPosition}." : 'No stored ranking position.'],
            'customer_value' => ['score' => $customerValue, 'maximum' => 25, 'explanation' => 'Estimated customer value £'.number_format($profile->estimated_customer_value).' ('.str($profile->customer_value_band)->replace('_', ' ').').'],
            'site_manageability' => ['score' => $siteManageability, 'maximum' => 20, 'explanation' => "{$candidate->page_count} indexable pages."],
            'commercial_intent' => ['score' => $commercialIntent, 'maximum' => 15, 'explanation' => 'Ranking evidence came from the profile’s curated local commercial searches.'],
        ];

        return ['commercial_opportunity_score' => collect($breakdown)->sum('score'), 'commercial_score_breakdown' => $breakdown];
    }

    private function commercialOpportunityRanking(SeoProspectCandidate $candidate): ?SeoProspectRanking
    {
        return $candidate->rankings->first(fn (SeoProspectRanking $ranking): bool => $ranking->position >= 8 && $ranking->position <= 30)
            ?? $candidate->rankings->first(fn (SeoProspectRanking $ranking): bool => $ranking->position >= 31 && $ranking->position <= 50)
            ?? $candidate->rankings->sortBy('position')->first();
    }

    /** @return array{score: int, maximum: int, explanation: string, evidence: array<int, array<string, mixed>>} */
    private function rankingScore(SeoProspectCandidate $candidate): array
    {
        if ($candidate->rankings->isEmpty()) {
            return [
                'score' => 0,
                'maximum' => 40,
                'explanation' => 'No stored organic ranking evidence was available.',
                'evidence' => [],
            ];
        }

        $averagePosition = (float) $candidate->rankings->avg('position');
        $minimum = $candidate->search->minimum_position;
        $maximum = $candidate->search->maximum_position;
        $range = max(1, $maximum - $minimum);
        $score = (int) round(40 * max(0, min(1, ($maximum - $averagePosition) / $range)));

        return [
            'score' => $score,
            'maximum' => 40,
            'explanation' => sprintf('Average organic position %.1f across %d stored keyword%s.', $averagePosition, $candidate->rankings->count(), $candidate->rankings->count() === 1 ? '' : 's'),
            'evidence' => $candidate->rankings->map(fn (SeoProspectRanking $ranking): array => [
                'source' => 'seo_prospect_rankings',
                'id' => $ranking->id,
                'keyword' => $ranking->keyword,
                'position' => $ranking->position,
            ])->values()->all(),
        ];
    }

    /** @return array{score: int, maximum: int, explanation: string, evidence: array<int, array<string, mixed>>} */
    private function auditScore(SeoProspectCandidate $candidate): array
    {
        if ($candidate->audit_score === null) {
            return [
                'score' => 0,
                'maximum' => 25,
                'explanation' => 'No stored website audit score was available.',
                'evidence' => [],
            ];
        }

        $auditScore = (int) $candidate->audit_score;
        $score = (int) round((100 - $auditScore) * 0.25);

        return [
            'score' => $score,
            'maximum' => 25,
            'explanation' => sprintf('The stored website audit scored %d/100, leaving %d audit-opportunity points.', $auditScore, $score),
            'evidence' => [[
                'source' => 'seo_prospect_candidates',
                'field' => 'audit_score',
                'value' => $auditScore,
            ]],
        ];
    }

    /** @return array{score: int, maximum: int, explanation: string, evidence: array<int, array<string, mixed>>} */
    private function siteFitScore(SeoProspectCandidate $candidate): array
    {
        $pageCount = (int) $candidate->page_count;
        $score = match (true) {
            $pageCount === 0 => 0,
            $pageCount <= 10 => 20,
            $pageCount <= $candidate->search->maximum_pages => 15,
            default => 0,
        };

        return [
            'score' => $score,
            'maximum' => 20,
            'explanation' => sprintf('%d indexable page%s against the configured maximum of %d.', $pageCount, $pageCount === 1 ? '' : 's', $candidate->search->maximum_pages),
            'evidence' => [[
                'source' => 'seo_prospect_candidates.observations',
                'field' => 'indexable_page_count',
                'value' => $pageCount,
            ]],
        ];
    }

    /** @return array{score: int, maximum: int, explanation: string, evidence: array<int, array<string, mixed>>} */
    private function migrationScore(SeoProspectCandidate $candidate): array
    {
        $score = match ($candidate->migration_difficulty) {
            'easy' => 15,
            'medium' => 8,
            'hard' => 2,
            default => 0,
        };

        return [
            'score' => $score,
            'maximum' => 15,
            'explanation' => $candidate->migration_difficulty_reason ?: 'Migration difficulty could not be determined from the crawl.',
            'evidence' => [[
                'source' => 'seo_prospect_candidates',
                'field' => 'migration_difficulty',
                'value' => $candidate->migration_difficulty,
            ]],
        ];
    }

    /** @return array<int, array{type: string, summary: string, evidence: array<int, array<string, mixed>>}> */
    private function outreachObservations(SeoProspectCandidate $candidate): array
    {
        $observations = [];
        $bestRanking = $candidate->rankings->sortBy('position')->first();

        if ($bestRanking) {
            $observations[] = [
                'type' => 'ranking',
                'summary' => sprintf('Ranks #%d for “%s”.', $bestRanking->position, $bestRanking->keyword),
                'evidence' => [['source' => 'seo_prospect_rankings', 'id' => $bestRanking->id]],
            ];
        }

        $observations[] = [
            'type' => 'crawl',
            'summary' => sprintf('The crawl confirmed %d indexable page%s.', $candidate->page_count, $candidate->page_count === 1 ? '' : 's'),
            'evidence' => [['source' => 'seo_prospect_candidates.observations', 'field' => 'indexable_page_count']],
        ];

        $this->auditFindings($candidate)->each(function (array $finding) use (&$observations): void {
            $observations[] = [
                'type' => 'audit',
                'summary' => (string) ($finding['message'] ?? $finding['title'] ?? $finding['key']),
                'evidence' => [[
                    'source' => 'seo_prospect_candidates.audit_findings',
                    'index' => $finding['index'],
                    'key' => $finding['key'] ?? null,
                    'source_url' => $finding['source_url'] ?? null,
                ]],
            ];
        });

        return $observations;
    }

    /** @return Collection<int, array<string, mixed>> */
    private function auditFindings(SeoProspectCandidate $candidate): Collection
    {
        return collect($candidate->audit_findings ?? [])
            ->map(fn (array $finding, int $index): array => [...$finding, 'index' => $index])
            ->whereIn('severity', ['failed', 'warning'])
            ->sortBy(fn (array $finding): int => $finding['severity'] === 'failed' ? 0 : 1)
            ->take(3)
            ->values();
    }
}
