<?php

namespace App\Services;

use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\SeoWin;
use App\Models\Website;
use Illuminate\Database\Eloquent\Builder;

class SeoWinDetector
{
    public function detect(Website $website): void
    {
        if (! $website->is_active) {
            return;
        }
        $website->seoTargetKeywords()->whereNull('archived_at')->each(function (SeoTargetKeyword $target): void {
            $query = $this->observations($target);
            $ids = (clone $query)->selectRaw('MAX(id)')->groupBy('observed_at');
            $rows = (clone $query)->whereIn('id', $ids)->latest('observed_at')->limit(3)->get();
            if ($rows->count() < 3) {
                return;
            }
            [$latest, $first, $baseline] = $rows->all();
            if ($latest->status !== SeoTargetKeywordRanking::STATUS_RANKED || $first->status !== SeoTargetKeywordRanking::STATUS_RANKED
                || ! $latest->position || ! $first->position || $latest->observed_at->isFuture()
                || $latest->observed_at->lessThan(now()->subDays(14))
                || ($latest->provider_task_id !== null && $latest->provider_task_id === $first->provider_task_id)
                || ($first->provider_task_id !== null && $first->provider_task_id === $baseline->provider_task_id)
                || $first->observed_at->diffInDays($latest->observed_at) < 3
                || $first->observed_at->diffInDays($latest->observed_at) > 21
                || $baseline->observed_at->diffInDays($first->observed_at) > 60
                || $baseline->status === SeoTargetKeywordRanking::STATUS_FAILED) {
                return;
            }
            $history = (clone $query)->where('observed_at', '<', $first->observed_at)
                ->whereIn('status', [SeoTargetKeywordRanking::STATUS_RANKED, SeoTargetKeywordRanking::STATUS_NOT_FOUND]);
            $best = (clone $history)->where('status', SeoTargetKeywordRanking::STATUS_RANKED)->min('position');
            $worstConfirmation = max($latest->position, $first->position);
            $rule = match (true) {
                $baseline->position !== null && $baseline->position > 3 && $worstConfirmation <= 3 => 'top_3',
                $baseline->position !== null && $baseline->position > 10 && $worstConfirmation <= 10 => 'top_10',
                $baseline->status === SeoTargetKeywordRanking::STATUS_NOT_FOUND && $worstConfirmation <= 50 => 'newly_ranked',
                $best !== null && $best - $worstConfirmation >= 3 && (clone $history)->distinct()->count('observed_at') >= 3 => 'personal_best',
                default => null,
            };
            if (! $rule) {
                return;
            }
            $market = ['provider' => $latest->provider, 'location_code' => $latest->location_code, 'language_code' => $latest->language_code, 'device' => $latest->device];
            $fingerprint = hash('sha256', json_encode([$target->normalized_term, $market, $rule, $rule === 'personal_best' ? $worstConfirmation : null]));
            $achievement = match ($rule) {
                'top_3' => 'Entered the top 3',
                'top_10' => 'Entered the top 10',
                'newly_ranked' => 'Now ranking in the top 50',
                default => 'Best recorded ranking',
            };
            $observations = $rows->reverse()->map(fn ($row): array => ['id' => $row->id, 'observed_at' => $row->observed_at->toIso8601String(), 'status' => $row->status, 'position' => $row->position, 'url' => $row->ranking_url])->values()->all();
            $draft = 'Quick SEO update — “'.$target->term.'” '.match ($rule) {
                'top_3' => 'has moved into Google’s top 3',
                'top_10' => 'has moved into Google’s top 10',
                'newly_ranked' => 'is now showing in Google’s top 50 after previously being outside the top 100',
                default => 'has reached its best recorded Google ranking',
            }.'. Our desktop checks in the tracked market confirmed this across two separate dates, with the latest position at '.$latest->position.' on '.$latest->observed_at->format('j M Y').'. A nice step forward — we’ll keep an eye on how it holds up.';
            SeoWin::firstOrCreate(['website_id' => $target->website_id, 'fingerprint' => $fingerprint], [
                'seo_target_keyword_id' => $target->id, 'rule' => $rule, 'rule_version' => 1,
                'importance' => $rule === 'top_3' || $target->priority === 'high' ? 'high' : 'normal', 'confidence' => 'medium',
                'title' => $achievement.' · '.$target->term,
                'evidence' => ['keyword' => $target->term, 'priority' => $target->priority, 'market' => $market,
                    'observations' => $observations, 'historical_best_before' => $best,
                    'intended_url' => $target->intended_url, 'search_intent' => $target->search_intent,
                    'confirmation_days' => (int) $first->observed_at->diffInDays($latest->observed_at), 'traffic_impact' => null],
                'observed_at' => $first->observed_at, 'confirmed_at' => $latest->observed_at, 'client_draft' => $draft,
            ]);
        });
    }

    private function observations(SeoTargetKeyword $target): Builder
    {
        return SeoTargetKeywordRanking::where('seo_target_keyword_id', $target->id)->where('website_id', $target->website_id)
            ->where('provider', 'dataforseo')->where('location_code', (int) config('services.dataforseo.location_code'))
            ->where('language_code', (string) config('services.dataforseo.language_code'))->where('device', 'desktop');
    }
}
