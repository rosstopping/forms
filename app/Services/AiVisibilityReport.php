<?php

namespace App\Services;

use App\Models\AiVisibilityPrompt;
use App\Models\AiVisibilityResult;
use App\Models\Website;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

class AiVisibilityReport
{
    /** @return array<string, mixed> */
    public function forPeriod(Website $website, Carbon $start, Carbon $end, ?string $provider = null): array
    {
        $days = (int) $start->copy()->startOfDay()->diffInDays($end->copy()->startOfDay()) + 1;
        $base = AiVisibilityResult::query()->where('website_id', $website->id)->where('status', 'completed')->when($provider, fn ($query) => $query->where('provider', $provider));
        $current = (clone $base)->whereBetween('checked_at', [$start, $end])->orderBy('checked_at')->orderBy('id')->get();
        $previous = (clone $base)->whereBetween('checked_at', [$start->copy()->subDays($days), $end->copy()->subDays($days)])->orderBy('checked_at')->orderBy('id')->get();
        $metrics = $this->summarize($current);
        $prior = $this->summarize($previous);
        $cohortChanged = $current->countBy('cohort')->sortKeys()->all() !== $previous->countBy('cohort')->sortKeys()->all();
        $comparable = $current->isNotEmpty() && $previous->isNotEmpty() && ! $cohortChanged;
        $providerMetrics = $current->concat($previous)->pluck('provider')->unique()->mapWithKeys(function (string $key) use ($current, $previous): array {
            $now = $this->summarize($current->where('provider', $key));
            $before = $this->summarize($previous->where('provider', $key));

            $comparable = $now['completed_checks'] > 0 && $before['completed_checks'] > 0 && $current->where('provider', $key)->countBy('cohort')->sortKeys()->all() === $previous->where('provider', $key)->countBy('cohort')->sortKeys()->all();

            return [$key => [...$now, 'previous_score' => $before['score'], 'comparable' => $comparable, 'change' => $comparable ? round($now['score'] - $before['score'], 1) : null]];
        })->all();
        $changes = $this->changes($current, $previous);
        $opportunities = $this->opportunities($website, $current, $changes, $end);
        $failed = AiVisibilityResult::query()->where('website_id', $website->id)->where('status', 'failed')->whereBetween('updated_at', [$start, $end])->when($provider, fn ($query) => $query->where('provider', $provider))->count();
        $summary = $metrics['completed_checks'] === 0 ? 'No completed AI visibility checks are available for this period.' : 'Your business appeared in '.$metrics['brand_appearances'].' of '.$metrics['completed_checks'].' completed AI checks: '.$metrics['score'].'% visibility. Its website was cited in '.$metrics['citation_rate'].'% of checks.';
        if ($comparable) {
            $summary .= ' Visibility was '.$prior['score'].'% in the previous equivalent period, with '.$prior['brand_appearances'].' appearances in '.$prior['completed_checks'].' completed checks.';
        } elseif ($previous->isNotEmpty()) {
            $summary .= ' The previous score was '.$prior['score'].'%, but prompts, providers, model, brand identifiers or successful-check coverage changed; this is not a like-for-like trend.';
        }
        $strongest = collect($providerMetrics)->whereNotNull('score')->sortByDesc('score')->keys()->first();
        if ($strongest !== null) {
            $summary .= ' Visibility was strongest on '.(['openai' => 'OpenAI', 'gemini' => 'Gemini', 'perplexity' => 'Perplexity'][$strongest] ?? $strongest).' at '.$providerMetrics[$strongest]['score'].'%.';
        }
        foreach ($providerMetrics as $key => $providerMetric) {
            if ($providerMetric['change'] !== null && $providerMetric['change'] != 0) {
                $changes[] = ['type' => $providerMetric['change'] > 0 ? 'provider_improved' : 'provider_declined', 'provider' => $key, 'summary' => (['openai' => 'OpenAI', 'gemini' => 'Gemini', 'perplexity' => 'Perplexity'][$key] ?? $key).' visibility changed from '.$providerMetric['previous_score'].'% to '.$providerMetric['score'].'% on comparable checks.'];
            }
        }
        if ($failed > 0) {
            $summary .= ' '.$failed.' failed checks were excluded.';
        }
        foreach (array_slice($changes, 0, 3) as $change) {
            $summary .= ' '.$change['summary'];
        }

        return [...$metrics, 'previous_score' => $prior['score'], 'previous_completed_checks' => $prior['completed_checks'], 'previous_brand_appearances' => $prior['brand_appearances'], 'previous_citation_rate' => $prior['citation_rate'], 'change' => $comparable ? round($metrics['score'] - $prior['score'], 1) : null, 'comparable' => $comparable, 'coverage_changed' => $cohortChanged, 'providers' => $providerMetrics, 'notable_changes' => $changes, 'opportunities' => $opportunities, 'failed_checks' => $failed, 'summary' => $summary,
            'period' => ['start' => $start->toDateString(), 'end' => $end->toDateString(), 'comparison_start' => $start->copy()->subDays($days)->toDateString(), 'comparison_end' => $end->copy()->subDays($days)->toDateString()]];
    }

    /** @param Collection<int, AiVisibilityResult> $results
     * @return array<string, mixed>
     */
    public function summarize(Collection $results): array
    {
        $results = $results->where('status', 'completed');
        $checks = $results->count();
        $appearances = $results->where('brand_mentioned', true)->count();
        $cited = $results->where('website_cited', true)->count();
        $competitors = $results->flatMap(fn ($result) => collect($result->competitors ?? [])->unique('key')->map(fn ($competitor) => [...$competitor, 'result_id' => $result->id, 'prompt_id' => $result->ai_visibility_prompt_id]))
            ->groupBy('key')->map(fn ($rows, $key) => ['key' => $key, 'name' => $rows->first()['name'], 'appearances' => $rows->count(), 'visibility' => round($rows->count() / max(1, $checks) * 100, 1), 'prompt_ids' => $rows->pluck('prompt_id')->unique()->values()->all()])->sortByDesc('appearances')->values()->all();
        $positions = $results->where('brand_mentioned', true)->whereNotNull('brand_position');

        return ['score' => $checks > 0 ? round($appearances / $checks * 100, 1) : null, 'completed_checks' => $checks, 'brand_appearances' => $appearances, 'prompts_visible' => $results->where('brand_mentioned', true)->pluck('ai_visibility_prompt_id')->unique()->count(), 'citation_rate' => $checks > 0 ? round($cited / $checks * 100, 1) : null, 'cited_checks' => $cited, 'average_position' => $positions->isNotEmpty() ? round($positions->avg('brand_position'), 1) : null, 'position_checks' => $positions->count(), 'competitor_count' => count($competitors), 'competitors' => $competitors];
    }

    /** @return array<int, array{snapshot_date: string, score: float, completed_checks: int}> */
    public function trend(Website $website, Carbon $start, Carbon $end, ?string $provider = null, ?int $promptId = null): array
    {
        return AiVisibilityResult::query()->where('website_id', $website->id)->where('status', 'completed')->whereBetween('checked_at', [$start, $end])
            ->when($provider, fn ($query) => $query->where('provider', $provider))->when($promptId, fn ($query) => $query->where('ai_visibility_prompt_id', $promptId))
            ->orderBy('checked_at')->get(['id', 'status', 'checked_at', 'brand_mentioned'])
            ->groupBy(fn ($result) => $result->checked_at->toDateString())->map(fn ($rows, $date) => ['snapshot_date' => $date, 'score' => round($rows->where('brand_mentioned', true)->count() / $rows->count() * 100, 1), 'completed_checks' => $rows->count()])->values()->all();
    }

    /** @param Collection<int, AiVisibilityResult> $current
     * @param  Collection<int, AiVisibilityResult>  $previous
     * @return array<int, array<string, mixed>>
     */
    private function changes(Collection $current, Collection $previous): array
    {
        $prior = $previous->keyBy('cohort');
        $changes = [];
        foreach ($current->keyBy('cohort') as $key => $result) {
            $old = $prior->get($key);
            if (! $old) {
                continue;
            }
            foreach (['brand_mentioned' => ['appearance_gained', 'appearance_lost'], 'website_cited' => ['citation_gained', 'citation_lost']] as $field => [$gain, $loss]) {
                if ($result->$field !== $old->$field) {
                    $type = $result->$field ? $gain : $loss;
                    $changes[] = ['type' => $type, 'prompt_id' => $result->ai_visibility_prompt_id, 'provider' => $result->provider, 'result_id' => $result->id, 'previous_result_id' => $old->id, 'summary' => ucfirst(str_replace('_', ' ', $type)).' on '.ucfirst($result->provider).' for “'.$result->prompt_snapshot.'”.'];
                }
            }
        }
        if ($previous->isNotEmpty()) {
            $oldNames = $previous->flatMap(fn ($result) => collect($result->competitors ?? [])->pluck('key'))->unique();
            foreach ($current->flatMap(fn ($result) => $result->competitors ?? [])->unique('key')->reject(fn ($competitor) => $oldNames->contains($competitor['key']))->take(5) as $competitor) {
                $changes[] = ['type' => 'new_competitor', 'summary' => $competitor['name'].' appeared in this period’s responses but not in the previous sample.'];
            }
        }

        return array_slice($changes, 0, 15);
    }

    /** @param Collection<int, AiVisibilityResult> $current
     * @param  array<int, array<string, mixed>>  $changes
     * @return array<int, array<string, mixed>>
     */
    private function opportunities(Website $website, Collection $current, array $changes, Carbon $end): array
    {
        $prompts = AiVisibilityPrompt::query()->where('website_id', $website->id)->where('active', true)->whereIn('id', $current->pluck('ai_visibility_prompt_id'))->with(['targetKeyword.rankings' => fn ($query) => $query->where('status', 'ranked')->where('device', 'desktop')->where('location_code', config('services.dataforseo.location_code'))->where('language_code', config('services.dataforseo.language_code'))->whereBetween('observed_at', [$end->copy()->subDays(30), $end])->latest('observed_at')])->get()->keyBy('id');
        $opportunities = [];
        foreach ($current->groupBy('ai_visibility_prompt_id') as $id => $results) {
            $prompt = $prompts->get($id);
            if (! $prompt) {
                continue;
            }
            $results = $results->where('prompt_fingerprint', $prompt->fingerprint);
            if ($results->isEmpty()) {
                continue;
            }
            $base = ['source' => 'ai_visibility', 'subject_id' => $prompt->id, 'prompt' => $prompt->prompt, 'evidence' => ['result_ids' => $results->pluck('id')->all(), 'completed_checks' => $results->count()], 'url' => route('admin.ai-visibility.show', [$website, $prompt])];
            $missing = $results->where('brand_mentioned', true)->isEmpty();
            $rank = $prompt->targetKeyword?->rankings->first();
            if ($missing) {
                $type = $rank && $rank->position <= 10 ? 'google_ai_gap' : 'missing_brand';
                $opportunities[] = [...$base, 'type' => $type, 'score' => $prompt->priority === 'high' ? 85 : 60, 'title' => 'Improve AI visibility for “'.$prompt->prompt.'”', 'reason' => ($type === 'google_ai_gap' ? 'The linked Google keyword ranks at position '.$rank->position.', but ' : '').'your business did not appear in '.$results->count().' completed AI checks.', 'google_position' => $rank?->position];
            } elseif ($results->where('brand_mentioned', true)->where('website_cited', false)->isNotEmpty()) {
                $opportunities[] = [...$base, 'type' => 'missing_citation', 'score' => 55, 'title' => 'Strengthen source visibility for “'.$prompt->prompt.'”', 'reason' => 'Your business was mentioned without its website being cited in '.$results->where('brand_mentioned', true)->where('website_cited', false)->count().' checks.'];
            }
            $higher = $results->whereNotNull('brand_position')->flatMap(fn ($result) => collect($result->competitors ?? [])->filter(fn ($competitor) => ($competitor['position'] ?? null) !== null && $competitor['position'] < $result->brand_position))->groupBy('key')->first(fn ($rows) => $rows->count() >= 2);
            if ($higher) {
                $opportunities[] = [...$base, 'type' => 'competitor_ahead', 'score' => 75, 'title' => 'Review competing recommendations for “'.$prompt->prompt.'”', 'reason' => $higher->first()['name'].' appeared earlier in the recommendation list in '.$higher->count().' checks.'];
            }
            if (collect($changes)->contains(fn ($change) => ($change['prompt_id'] ?? null) === $id && $change['type'] === 'appearance_lost')) {
                $opportunities[] = [...$base, 'type' => 'lost_appearance', 'score' => 90, 'title' => 'Review lost AI visibility for “'.$prompt->prompt.'”', 'reason' => 'The latest comparable response stopped mentioning your business.'];
            }
        }

        return collect($opportunities)->sortByDesc('score')->take(10)->values()->all();
    }
}
