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

        $candidate->loadMissing(['rankings', 'search']);
        $ranking = $this->rankingScore($candidate);
        $audit = $this->auditScore($candidate);
        $siteFit = $this->siteFitScore($candidate);
        $migration = $this->migrationScore($candidate);
        $breakdown = compact('ranking', 'audit', 'siteFit', 'migration');

        return [
            'opportunity_score' => collect($breakdown)->sum('score'),
            'score_breakdown' => $breakdown,
            'observations' => [
                ...($candidate->observations ?? []),
                'outreach' => $this->outreachObservations($candidate),
            ],
        ];
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
