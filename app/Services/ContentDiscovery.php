<?php

namespace App\Services;

use App\Models\CompetitorOpportunity;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\SeoTargetKeyword;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class ContentDiscovery
{
    public function discover(ContentPlan $plan): int
    {
        if (! $plan->discovery_enabled || ! $plan->website->is_active) {
            return 0;
        }

        return Cache::lock('content-discovery-'.$plan->website_id, 300)->get(function () use ($plan): int {
            $website = $plan->website;
            $targets = $website->seoTargetKeywords()->whereNull('archived_at')->get();
            $terms = $targets->pluck('normalized_term')->flatMap(fn (string $term): array => preg_split('/\W+/u', $term))
                ->filter(fn (string $term): bool => mb_strlen($term) >= 4)->unique();
            if ($terms->isEmpty()) {
                return 0;
            }
            $created = 0;
            $audits = app(CompetitorContentContext::class)->eligibleAudits($website)->latest('completed_at')->limit(5)->get();
            foreach ($audits as $audit) {
                $keywords = $audit->keywords()->where('comparison', 'missing')->where('search_volume', '>', 0)
                    ->whereIn('search_intent', ['informational', 'commercial', 'transactional'])->orderByDesc('search_volume')->limit(100)->get();
                foreach ($keywords as $keyword) {
                    $normal = SeoTargetKeyword::normalize($keyword->keyword);
                    if ($terms->intersect(preg_split('/\W+/u', $normal))->isEmpty() || ! $keyword->ranking_url) {
                        continue;
                    }
                    $fingerprint = hash('sha256', $audit->location_code.'|'.$audit->language_code.'|'.$normal);
                    if (CompetitorOpportunity::where('website_id', $website->id)->where('fingerprint', $fingerprint)->exists() || ContentOpportunity::where('website_id', $website->id)->where('fingerprint', $fingerprint)->exists()) {
                        continue;
                    }
                    $destination = $targets->firstWhere('normalized_term', $normal)?->intended_url;
                    $opportunity = $audit->opportunities()->firstOrCreate(['fingerprint' => $fingerprint], [
                        'website_id' => $website->id,
                        'title' => Str::limit('Investigate coverage for '.$keyword->keyword, 255, ''),
                        'priority_score' => min(85, 40 + log10(max(1, $keyword->search_volume)) * 10),
                        'brief' => [
                            'title' => 'Investigate coverage for '.$keyword->keyword,
                            'primary_keyword' => $keyword->keyword,
                            'search_intent' => $keyword->search_intent,
                            'relevance' => 2,
                            'relevance_reason' => 'Shares a topic with configured business target keywords. Confirm audience fit and current coverage before approval.',
                            'purpose' => 'Assess an evidenced search opportunity and satisfy its user need with original useful content.',
                            'content_format' => 'article',
                            'existing_page_url' => $destination,
                            'source_urls' => [$keyword->ranking_url],
                            'observations' => ['The provider domain comparison found competitor visibility and no matching visibility for this website. This does not prove the website lacks a suitable page.'],
                            'ranking_hypotheses' => [], 'gaps' => [], 'improvements' => [], 'outline' => [],
                            'keywords' => [$keyword->only(['keyword', 'position', 'our_position', 'ranking_url', 'search_volume', 'search_intent'])],
                            'audit_id' => $audit->id, 'collected_at' => $audit->completed_at->toIso8601String(),
                            'data_source' => 'third_party_estimate', 'discovery_source' => 'saved_domain_comparison',
                            'competitor_domain' => $audit->competitor_domain, 'our_domain' => $audit->domain,
                            'location_code' => $audit->location_code, 'language_code' => $audit->language_code,
                            'requires_planning_approval' => true,
                        ],
                    ]);
                    $created += (int) $opportunity->wasRecentlyCreated;
                    if ($created >= 10) {
                        return $created;
                    }
                }
            }

            return $created;
        }) ?: 0;
    }
}
