<?php

namespace App\Services;

use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\SeoProspectCandidate;
use App\Models\SeoProspectSearch;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class SeoProspectImporter
{
    public function __construct(private PixelUrlNormalizer $urls) {}

    /** @param array<int, int> $candidateIds */
    public function import(SeoProspectSearch $search, array $candidateIds, User $user): int
    {
        $createdProspects = collect();

        $imported = DB::transaction(function () use ($search, $candidateIds, $user, $createdProspects): int {
            $search = SeoProspectSearch::query()->lockForUpdate()->findOrFail($search->id);
            $candidates = $search->candidates()->whereIn('id', $candidateIds)->lockForUpdate()->get();

            if ($candidates->count() !== count($candidateIds)) {
                throw ValidationException::withMessages(['candidate_ids' => 'One or more selected candidates do not belong to this search.']);
            }

            foreach ($candidates as $candidate) {
                if ($candidate->qualification_status !== 'suitable') {
                    throw ValidationException::withMessages(['candidate_ids' => "{$candidate->domain} is not a suitable candidate."]);
                }

                $prospect = $candidate->prospect ?: $this->findProspect($candidate->domain);

                if (! $prospect) {
                    $prospect = Prospect::query()->create([
                        'user_id' => $search->user_id,
                        'business_name' => $candidate->business_name ?: $candidate->domain,
                        'email' => data_get($candidate->contact_details, 'emails.0.value'),
                        'website_url' => $candidate->website_url,
                        'status' => 'researched',
                        'analysis_status' => 'pending',
                        'opportunity_score' => $candidate->opportunity_score,
                        'contact_details' => $candidate->contact_details,
                    ]);
                    $createdProspects->push($prospect);
                }

                $candidate->update(['prospect_id' => $prospect->id]);
                $this->recordEvidence($prospect, $candidate, $search, $user);
            }

            $search->update(['imported_count' => $search->candidates()->whereNotNull('prospect_id')->count()]);

            return $candidates->count();
        }, attempts: 3);

        $createdProspects->each(fn (Prospect $prospect) => AnalyzeProspect::dispatch($prospect)->afterCommit());

        return $imported;
    }

    private function findProspect(string $domain): ?Prospect
    {
        return Prospect::query()->whereNotNull('website_url')->where('website_url', 'like', '%'.$domain.'%')->lockForUpdate()->get()
            ->first(function (Prospect $prospect) use ($domain): bool {
                try {
                    return $this->urls->normalizeHost((string) parse_url($prospect->website_url, PHP_URL_HOST)) === $domain;
                } catch (InvalidArgumentException) {
                    return false;
                }
            });
    }

    private function recordEvidence(Prospect $prospect, SeoProspectCandidate $candidate, SeoProspectSearch $search, User $user): void
    {
        $candidate->loadMissing('rankings');
        $prospect->recordActivity('seo_opportunity_imported', 'Added to Outreach from an SEO opportunity search.', $user)->update([
            'metadata' => [
                'seo_prospect_search_id' => $search->id,
                'seo_prospect_candidate_id' => $candidate->id,
                'domain' => $candidate->domain,
                'opportunity_score' => $candidate->opportunity_score,
                'score_breakdown' => $candidate->score_breakdown,
                'observations' => data_get($candidate->observations, 'outreach', []),
                'rankings' => $candidate->rankings->map->only(['id', 'keyword', 'position', 'ranking_url', 'checked_at'])->values()->all(),
            ],
        ]);
    }
}
