<?php

namespace App\Jobs;

use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectSearch;
use App\Services\SeoOpportunityScoringService;
use App\Services\SeoProspectCandidateAnalyzer;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Throwable;

class AnalyzeSeoProspectCandidate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 240;

    public function __construct(public SeoProspectCandidate $candidate) {}

    public function uniqueId(): string
    {
        return (string) $this->candidate->id;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [30, 120];
    }

    public function handle(SeoProspectCandidateAnalyzer $analyzer, SeoOpportunityScoringService $scoringService): void
    {
        $this->candidate->update(['qualification_status' => 'analyzing', 'analysis_error' => null]);
        $this->candidate->update([
            ...$analyzer->analyze($this->candidate),
            'analysis_error' => null,
            'analyzed_at' => now(),
        ]);
        $this->candidate->refresh()->load(['rankings', 'search']);
        $this->candidate->update($scoringService->score($this->candidate));
        $this->updateSearchProgress();
    }

    public function failed(?Throwable $exception): void
    {
        $this->candidate->update([
            'qualification_status' => 'analysis_failed',
            'migration_difficulty' => 'unknown',
            'migration_difficulty_reason' => 'Candidate analysis could not be completed.',
            'analysis_error' => $exception?->getMessage(),
            'opportunity_score' => null,
            'score_breakdown' => null,
            'analyzed_at' => now(),
        ]);
        $this->updateSearchProgress();
    }

    private function updateSearchProgress(): void
    {
        DB::transaction(function (): void {
            $search = SeoProspectSearch::query()->lockForUpdate()->find($this->candidate->seo_prospect_search_id);

            if (! $search) {
                return;
            }

            $pending = $search->candidates()->whereIn('qualification_status', ['pending_analysis', 'analyzing'])->exists();
            $failed = $search->candidates()->where('qualification_status', 'analysis_failed')->exists();
            $search->update([
                'status' => $pending ? 'analyzing' : (($failed || filled($search->error)) ? 'analyzed_with_errors' : 'analyzed'),
                'suitable_count' => $search->candidates()->where('qualification_status', 'suitable')->count(),
                'completed_at' => $pending ? $search->completed_at : now(),
            ]);
        });
    }
}
