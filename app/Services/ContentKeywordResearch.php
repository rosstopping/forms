<?php

namespace App\Services;

use App\Models\CompetitorOpportunity;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\SeoTargetKeyword;
use App\Services\DataForSEO\DataForSEOClient;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class ContentKeywordResearch
{
    public function __construct(private DataForSEOClient $provider) {}

    public function research(ContentPlan $candidate): bool
    {
        $reservation = DB::transaction(function () use ($candidate): ?array {
            $plan = ContentPlan::lockForUpdate()->findOrFail($candidate->id);
            if (! $plan->keyword_research_enabled || ! app(CompetitorResearchAutomation::class)->allowance($plan->website)
                || ! config('services.dataforseo.login') || ! config('services.dataforseo.password')
                || $plan->keyword_researched_at?->gt(now()->subDays(7))) {
                return null;
            }
            $targets = $plan->website->seoTargetKeywords()->whereNull('archived_at')->orderBy('id')->get();
            $seeds = $targets->filter(fn ($target): bool => mb_strlen($target->term) <= 100)->values();
            if ($seeds->isEmpty()) {
                return null;
            }
            $previous = $seeds->search(fn ($target): bool => $target->id === ($plan->keyword_research['seed_id'] ?? null));
            $seed = $seeds->get($previous === false ? 0 : ($previous + 1) % $seeds->count());
            $reservation = ['requested_at' => now()->toIso8601String(), 'seed_id' => $seed->id, 'seed' => $seed->normalized_term,
                'business_note' => Str::limit((string) $seed->note, 500, ''),
                'location_code' => (int) config('services.dataforseo.location_code'), 'language_code' => (string) config('services.dataforseo.language_code')];
            $plan->update(['keyword_researched_at' => now(), 'keyword_research' => [...$reservation, 'status' => 'pending']]);

            return $reservation;
        });
        if (! $reservation) {
            return false;
        }
        try {
            $response = $this->provider->post('dataforseo_labs/google/related_keywords/live', [
                'keyword' => $reservation['seed'], 'location_code' => $reservation['location_code'], 'language_code' => $reservation['language_code'],
                'depth' => 1, 'limit' => 20, 'include_seed_keyword' => false, 'include_serp_info' => false,
            ], 30);
            $result = data_get($response->results, '0', []);
            if (($result['location_code'] ?? null) !== $reservation['location_code'] || ($result['language_code'] ?? null) !== $reservation['language_code']
                || SeoTargetKeyword::normalize($result['seed_keyword'] ?? '') !== $reservation['seed']) {
                throw new RuntimeException('Keyword research returned an unexpected market or seed.');
            }
            $created = DB::transaction(function () use ($candidate, $reservation, $result): int {
                $plan = ContentPlan::lockForUpdate()->findOrFail($candidate->id);
                if (! $plan->keyword_research_enabled || ! app(CompetitorResearchAutomation::class)->allowance($plan->website)) {
                    return 0;
                }
                $targets = $plan->website->seoTargetKeywords()->whereNull('archived_at')->get();
                if (! $targets->contains('id', $reservation['seed_id'])) {
                    return 0;
                }
                $tokens = collect(preg_split('/\W+/u', $reservation['seed']))->filter(fn (string $word): bool => mb_strlen($word) >= 4);
                $created = 0;
                foreach (array_slice($result['items'] ?? [], 0, 20) as $item) {
                    $data = $item['keyword_data'] ?? [];
                    $term = is_string($data['keyword'] ?? null) ? SeoTargetKeyword::normalize($data['keyword']) : '';
                    $volume = data_get($data, 'keyword_info.search_volume');
                    $intent = data_get($data, 'search_intent_info.main_intent');
                    if ($term === '' || mb_strlen($term) > 200 || $term === $reservation['seed'] || ! is_numeric($volume) || $volume <= 0
                        || ! in_array($intent, ['informational', 'commercial', 'transactional'], true)
                        || ($data['location_code'] ?? null) !== $reservation['location_code'] || ($data['language_code'] ?? null) !== $reservation['language_code']
                        || $tokens->intersect(preg_split('/\W+/u', $term))->isEmpty()) {
                        continue;
                    }
                    $fingerprint = hash('sha256', $reservation['location_code'].'|'.$reservation['language_code'].'|'.$term);
                    if (CompetitorOpportunity::where('website_id', $plan->website_id)->where('fingerprint', $fingerprint)->exists()) {
                        continue;
                    }
                    $target = $targets->firstWhere('normalized_term', $term);
                    $brief = ['primary_keyword' => $term, 'search_intent' => $intent, 'purpose' => 'Answer a related audience need with useful original content, after reviewing existing coverage.',
                        'relevance' => 2, 'relevance_reason' => 'Related to the configured business target “'.$reservation['seed'].'”. Staff must confirm business fit and coverage.',
                        'business_note' => $reservation['business_note'], 'existing_page_url' => $target?->intended_url, 'content_format' => 'article',
                        'search_volume' => (int) $volume, 'keyword_difficulty' => data_get($data, 'keyword_properties.keyword_difficulty'),
                        'provider_updated_at' => data_get($data, 'keyword_info.last_updated_time'),
                        'collected_at' => now()->toIso8601String(), 'location_code' => $reservation['location_code'], 'language_code' => $reservation['language_code'],
                        'discovery_source' => 'related_keywords', 'data_source' => 'third_party_estimate', 'seed_keyword' => $reservation['seed'],
                        'requires_planning_approval' => true, 'observations' => ['Related search estimates support investigation; they do not prove missing coverage, business relevance or achievable rankings.']];
                    $opportunity = ContentOpportunity::firstOrCreate(['website_id' => $plan->website_id, 'fingerprint' => $fingerprint], [
                        'title' => Str::limit('Explore '.$term, 255, ''), 'brief' => $brief, 'collected_at' => now(),
                        'priority_score' => (int) min(80, 35 + log10(max(1, $volume)) * 10),
                    ]);
                    $created += (int) $opportunity->wasRecentlyCreated;
                    if ($created >= 10) {
                        break;
                    }
                }

                return $created;
            });
            $candidate->update(['keyword_research' => [...$reservation, 'status' => 'completed', 'created' => $created,
                'collected_at' => now()->toIso8601String(), 'provider_cost_usd' => $response->cost, 'provider_task_id' => $response->taskId]]);
        } catch (Throwable $exception) {
            report($exception);
            $candidate->update(['keyword_research' => [...$reservation, 'status' => 'failed', 'provider_cost_usd' => isset($response) ? $response->cost : null,
                'error' => 'Keyword discovery was unavailable. To avoid duplicate purchases, another attempt is eligible after seven days.']]);
        }

        return true;
    }
}
