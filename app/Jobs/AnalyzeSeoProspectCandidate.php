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
        $reusableAnalysis = SeoProspectCandidate::query()
            ->where('domain', $this->candidate->domain)
            ->whereKeyNot($this->candidate->id)
            ->whereIn('qualification_status', ['suitable', 'too_large', 'unsuitable'])
            ->where('analyzed_at', '>=', now()->subDays(30))
            ->latest('analyzed_at')
            ->first();
        $analysis = $reusableAnalysis
            ? $reusableAnalysis->only(['page_count', 'audit_score', 'audit_findings', 'contact_details', 'migration_difficulty', 'migration_difficulty_reason', 'observations', 'qualification_status'])
            : $analyzer->analyze($this->candidate);
        $this->candidate->update([...$analysis, 'analysis_error' => null, 'analyzed_at' => now()]);
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
        $automaticSearch = DB::transaction(function (): ?SeoProspectSearch {
            $search = SeoProspectSearch::query()->lockForUpdate()->find($this->candidate->seo_prospect_search_id);

            if (! $search) {
                return null;
            }

            $pending = $search->candidates()->whereIn('qualification_status', ['pending_analysis', 'analyzing'])->exists();
            $failed = $search->candidates()->where('qualification_status', 'analysis_failed')->exists();
            $search->update([
                'status' => $pending ? 'analyzing' : (($failed || filled($search->error)) ? 'analyzed_with_errors' : 'analyzed'),
                'suitable_count' => $search->candidates()->where('qualification_status', 'suitable')->count(),
                'completed_at' => $pending ? $search->completed_at : now(),
            ]);

            if (! $pending && $search->automated && $search->automatic_import_dispatched_at === null) {
                $search->update(['automatic_import_dispatched_at' => now()]);

                return $search;
            }

            return null;
        });

        if ($automaticSearch) {
            try {
                ImportAutomaticSeoProspects::dispatch($automaticSearch);
            } catch (Throwable $exception) {
                $automaticSearch->update(['automatic_import_dispatched_at' => null]);

                throw $exception;
            }
        }
    }
}
