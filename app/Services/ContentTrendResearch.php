<?php

namespace App\Services;

use App\Models\CompetitorOpportunity;
use App\Models\ContentOpportunity;
use App\Models\ContentPlan;
use App\Models\SeoTargetKeyword;
use App\Services\DataForSEO\DataForSEOClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

class ContentTrendResearch
{
    public function __construct(private DataForSEOClient $provider) {}

    public function research(ContentPlan $candidate): bool
    {
        $reservation = DB::transaction(function () use ($candidate): ?array {
            $plan = ContentPlan::lockForUpdate()->findOrFail($candidate->id);
            if (! $plan->trend_research_enabled || ! app(CompetitorResearchAutomation::class)->allowance($plan->website)
                || ! config('services.dataforseo.login') || ! config('services.dataforseo.password')
                || $plan->trend_researched_at?->gt(now()->subDays(7))) {
                return null;
            }
            $keywords = CompetitorOpportunity::where('website_id', $plan->website_id)->where('status', 'open')->whereIn('competitor_audit_id', app(CompetitorContentContext::class)->eligibleAudits($plan->website)->select('id'))->where('brief->relevance', '>=', 2)->orderByDesc('priority_score')->limit(5)->get()->pluck('brief.primary_keyword')
                ->merge(ContentOpportunity::where('website_id', $plan->website_id)->where('status', 'open')->where('brief->location_code', (int) config('services.dataforseo.location_code'))->where('brief->language_code', (string) config('services.dataforseo.language_code'))->where('collected_at', '>=', now()->subDays(30))->orderByDesc('priority_score')->limit(5)->get()->pluck('brief.primary_keyword'))
                ->merge($plan->website->seoTargetKeywords()->whereNull('archived_at')->orderByRaw("CASE WHEN priority = 'high' THEN 0 ELSE 1 END")->orderBy('id')->pluck('term'))
                ->filter(fn ($term): bool => is_string($term) && mb_strlen($term) <= 100)
                ->map(SeoTargetKeyword::normalize(...))->unique()->take(5)->values()->all();
            if ($keywords === []) {
                return null;
            }
            $reservation = ['requested_at' => now()->toIso8601String(), 'location_code' => (int) config('services.dataforseo.location_code'),
                'language_code' => (string) config('services.dataforseo.language_code'), 'keywords' => $keywords];
            $plan->update(['trend_researched_at' => now(), 'trend_research' => [...$reservation, 'status' => 'pending']]);

            return $reservation;
        });
        if (! $reservation) {
            return false;
        }
        try {
            $response = $this->provider->post('keywords_data/dataforseo_trends/explore/live', [
                'keywords' => $reservation['keywords'], 'location_code' => $reservation['location_code'], 'type' => 'web',
                'date_from' => now()->subYear()->toDateString(), 'date_to' => now()->subDays(3)->toDateString(),
            ], 30);
            if ((int) data_get($response->results, '0.location_code') !== $reservation['location_code']) {
                throw new \RuntimeException('Trend provider returned an unexpected location.');
            }
            $graph = collect(data_get($response->results, '0.items', []))->firstWhere('type', 'dataforseo_trends_graph');
            $terms = [];
            foreach ($reservation['keywords'] as $term) {
                $index = array_search($term, array_map(SeoTargetKeyword::normalize(...), $graph['keywords'] ?? []), true);
                $points = $index === false ? collect() : collect($graph['data'] ?? [])
                    ->filter(fn ($point): bool => is_string($point['date_to'] ?? null) && $point['date_to'] <= now()->subDays(3)->toDateString())
                    ->sortBy('date_to')->take(-8)->values();
                $values = $points->map(fn ($point) => $point['values'][$index] ?? null);
                $complete = $values->count() === 8 && $values->every(fn ($value): bool => is_numeric($value) && $value > 0 && $value <= 100);
                if ($complete) {
                    $lengths = $points->map(fn ($point): int => (int) Carbon::parse($point['date_from'])->diffInDays(Carbon::parse($point['date_to'])) + 1);
                    $complete = $lengths->unique()->count() === 1 && $lengths->first() > 0;
                    foreach ($points as $offset => $point) {
                        if ($offset > 0 && Carbon::parse($points[$offset - 1]['date_to'])->addDay()->toDateString() !== $point['date_from']) {
                            $complete = false;
                        }
                    }
                }
                $before = $complete ? $values->take(4)->avg() : null;
                $after = $complete ? $values->slice(4)->avg() : null;
                $delta = $complete ? round($after - $before, 1) : null;
                $terms[$term] = ['state' => ! $complete ? 'insufficient_data' : ($delta >= 10 ? 'rising' : ($delta <= -10 ? 'falling' : 'stable')),
                    'previous_index' => $before, 'recent_index' => $after, 'index_change' => $delta,
                    'points' => $points->map(fn ($point): array => ['start' => $point['date_from'] ?? null, 'end' => $point['date_to'], 'index' => $point['values'][$index] ?? null])->all()];
            }
            $candidate->update(['trend_research' => [...$reservation, 'status' => 'completed', 'collected_at' => now()->toIso8601String(),
                'provider_cost_usd' => $response->cost, 'provider_task_id' => $response->taskId, 'source' => 'third_party_relative_popularity', 'terms' => $terms]]);
        } catch (Throwable $exception) {
            report($exception);
            $candidate->update(['trend_research' => [...$reservation, 'status' => 'failed', 'provider_cost_usd' => isset($response) ? $response->cost : null, 'error' => 'Trend research was unavailable. No automatic retry will purchase duplicate research; another attempt is eligible after seven days.']]);
        }

        return true;
    }
}
