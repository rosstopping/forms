<?php

namespace App\Services;

use App\Jobs\CheckAiVisibility;
use App\Models\AiVisibilityPrompt;
use App\Models\AiVisibilityResult;
use App\Models\AiVisibilitySetting;
use App\Models\Website;
use Illuminate\Support\Facades\DB;

class AiVisibilityScheduler
{
    public function __construct(private AiVisibilityProviderRegistry $providers, private AiVisibilityAnalyzer $analyzer) {}

    public function dispatchDue(): int
    {
        $count = 0;
        AiVisibilitySetting::query()->where('enabled', true)->with('website.owner')->eachById(function (AiVisibilitySetting $settings) use (&$count): void {
            $count += $this->queue($settings->website);
        });

        return $count;
    }

    public function queue(Website $website, ?AiVisibilityPrompt $onlyPrompt = null): int
    {
        return DB::transaction(function () use ($website, $onlyPrompt): int {
            $settings = AiVisibilitySetting::query()->where('website_id', $website->id)->lockForUpdate()->first();
            if (! $settings?->enabled || ! $website->is_active || ! $website->owner?->hasActiveMembership()) {
                return 0;
            }
            $prompts = AiVisibilityPrompt::query()->where('website_id', $website->id)->where('active', true)->orderByRaw("CASE WHEN priority = 'high' THEN 0 ELSE 1 END")->orderBy('id')->limit(max(0, (int) config('ai_visibility.max_active_prompts')))->get();
            if ($onlyPrompt) {
                $prompts = $prompts->where('id', $onlyPrompt->id);
            }
            $identity = $this->analyzer->identity($website, $settings);
            $queued = 0;
            foreach ($prompts as $prompt) {
                foreach ($settings->providers ?? [] as $key) {
                    $provider = $this->providers->get($key);
                    if (! $provider->available()) {
                        continue;
                    }
                    $latest = $prompt->results()->where('provider', $key)->latest('created_at')->first();
                    if ($latest && in_array($latest->status, ['queued', 'running'], true) && $latest->created_at->lt(now()->subDays(6))) {
                        $latest->update(['status' => 'failed', 'error' => 'The check expired before completing.']);
                    }
                    if ($latest && in_array($latest->status, ['queued', 'running'], true) && $latest->updated_at->lt(now()->subDay()) && $latest->attempts < 3) {
                        CheckAiVisibility::dispatch($latest)->afterCommit();
                        $queued++;

                        continue;
                    }
                    if ($latest && in_array($latest->status, ['queued', 'running'], true) && $latest->updated_at->lt(now()->subDay()) && $latest->attempts >= 3) {
                        $latest->update(['status' => 'failed', 'error' => 'This check exhausted its attempts without completing.']);
                    }
                    if ($latest && ($latest->created_at->gt(now()->subDays($settings->frequency_days)) || in_array($latest->status, ['queued', 'running'], true))) {
                        continue;
                    }
                    $model = $provider->model();
                    if ($prompt->results()->where('provider', $key)->whereDate('period_start', today()->startOfWeek())->exists()) {
                        continue;
                    }
                    $result = AiVisibilityResult::query()->firstOrCreate(['ai_visibility_prompt_id' => $prompt->id, 'provider' => $key, 'period_start' => today()->startOfWeek()->toDateString()], [
                        'website_id' => $website->id, 'model' => $model, 'prompt_snapshot' => $prompt->prompt, 'prompt_fingerprint' => $prompt->fingerprint,
                        'identity_snapshot' => $identity, 'cohort' => hash('sha256', json_encode([$prompt->fingerprint, $identity, $model, $key], JSON_THROW_ON_ERROR)),
                    ]);
                    if ($result->wasRecentlyCreated) {
                        CheckAiVisibility::dispatch($result)->afterCommit();
                        $queued++;
                    }
                }
            }

            return $queued;
        });
    }
}
