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
        return $this->queueWithFeedback($website, $onlyPrompt)['queued'];
    }

    /** @return array{queued: int, message: string} */
    public function queueWithFeedback(Website $website, ?AiVisibilityPrompt $onlyPrompt = null): array
    {
        return DB::transaction(function () use ($website, $onlyPrompt): array {
            $settings = AiVisibilitySetting::query()->where('website_id', $website->id)->lockForUpdate()->first();
            if (! $settings?->enabled) {
                return ['queued' => 0, 'message' => 'AI tracking is paused. Turn on tracking in Business details and tracking settings.'];
            }
            if (! $website->is_active) {
                return ['queued' => 0, 'message' => 'This website is inactive. Reactivate the website before running AI checks.'];
            }
            if (! $website->owner?->hasActiveMembership()) {
                return ['queued' => 0, 'message' => 'AI checks require an active membership for the website owner. Ask the owner to review their membership, or contact support.'];
            }
            if (! in_array('openai', $settings->providers ?? [], true)) {
                return ['queued' => 0, 'message' => 'The AI provider is missing from your tracking settings. Save Business details and tracking settings again to enable OpenAI checks.'];
            }
            if (! $this->providers->get('openai')->available()) {
                return ['queued' => 0, 'message' => 'OpenAI checks are temporarily unavailable. Please contact support to check the AI service configuration.'];
            }
            if ((int) config('ai_visibility.max_active_prompts') <= 0) {
                return ['queued' => 0, 'message' => 'AI checks are disabled by the question limit. Please contact support.'];
            }
            if ($onlyPrompt && (! $onlyPrompt->active || $onlyPrompt->trashed())) {
                return ['queued' => 0, 'message' => 'This question is not active. Reactivate it before running a check.'];
            }
            $prompts = AiVisibilityPrompt::query()->where('website_id', $website->id)->where('active', true)->orderByRaw("CASE WHEN priority = 'high' THEN 0 ELSE 1 END")->orderBy('id')->limit(max(0, (int) config('ai_visibility.max_active_prompts')))->get();
            if ($onlyPrompt) {
                $prompts = $prompts->where('id', $onlyPrompt->id);
            }
            if ($prompts->isEmpty()) {
                return ['queued' => 0, 'message' => $onlyPrompt
                    ? 'This question is outside the active question limit. Disable another question or increase its priority before checking again.'
                    : 'No active questions are available. Add a question or reactivate an existing question before checking again.'];
            }
            $pending = false;
            $identity = $this->analyzer->identity($website, $settings);
            $queued = 0;
            foreach ($prompts as $prompt) {
                foreach (array_intersect($settings->providers ?? [], ['openai']) as $key) {
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
                    if ($latest && in_array($latest->status, ['queued', 'running'], true)) {
                        $pending = true;
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

            return ['queued' => $queued, 'message' => $queued > 0
                ? $queued.' AI checks queued.'
                : ($pending
                    ? 'AI checks are already queued or running. Results will appear when they finish; no additional checks are due yet.'
                    : 'No new AI checks are due yet. Checks follow your tracking frequency, with at most one check per question each week, including failed attempts.')];
        });
    }
}
