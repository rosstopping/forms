<?php

namespace App\Services;

use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\SeoImpact;
use App\Models\SeoTargetKeyword;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ContentNoWorkRecovery
{
    public const MAX_ATTEMPTS = 3;

    /**
     * Keep every paid attempt while returning only requests verified to have delivered no changes.
     *
     * @param  array<string, mixed>  $evidence
     */
    public function recover(ContentGeneration $generation, string $reason, array $evidence): void
    {
        if (($evidence['classification'] ?? null) !== 'no_work' || ($evidence['changed_files'] ?? null) !== 0) {
            return;
        }

        DB::transaction(function () use ($generation, $reason, $evidence): void {
            $plan = ContentPlan::query()->lockForUpdate()->findOrFail($generation->content_plan_id);
            $generation->refresh();
            if ($generation->status !== ContentGeneration::STATUS_RUNNING || $generation->merged_at
                || ! in_array($generation->copilot_task_state, ['completed', 'failed', 'cancelled'], true)) {
                return;
            }
            $requests = $generation->contentRequests()->lockForUpdate()->get();
            if ($requests->contains(fn ($request): bool => $request->manual_started_at !== null || $request->pixel_processed_at !== null || $request->optimisations()->exists())) {
                $generation->update(['status' => ContentGeneration::STATUS_FAILED, 'completed_at' => now(), 'completion_evidence' => $evidence, 'error' => 'GitHub has no changes, but this request has other work. Staff review is required.']);

                return;
            }
            foreach ($requests as $request) {
                $request->update([
                    'content_generation_id' => null, 'picked_up_at' => null,
                    'held_at' => now(), 'hold_reason' => Str::limit('No changes delivered. Review before releasing: '.$reason, 500, ''),
                    'preflight' => ['state' => 'no_work', 'reason' => $reason, 'generation_id' => $generation->id, 'checked_at' => now()->toIso8601String(), 'eligible_at' => null],
                ]);
                SeoImpact::where('content_request_id', $request->id)->where('content_generation_id', $generation->id)->whereNull('live_at')->update(['content_generation_id' => null]);
            }
            $targetSelection = $generation->completion_evidence ?? [];
            if ($generation->seo_target_keyword_id && isset($targetSelection['target_selected_at'])) {
                SeoTargetKeyword::whereKey($generation->seo_target_keyword_id)->where('last_selected_at', $targetSelection['target_selected_at'])
                    ->update(['last_selected_at' => $targetSelection['previous_target_selected_at'] ?? null]);
            }
            $generation->update([
                'status' => ContentGeneration::STATUS_NO_WORK,
                'skip_reason' => 'No changes delivered; untouched requests returned to the queue on hold. '.$reason,
                'completion_evidence' => [...$targetSelection, ...$evidence, 'request_ids' => $requests->modelKeys(),
                    'request_fingerprints' => $requests->map(fn ($request): string => hash('sha256', SeoTargetKeyword::normalize($request->instructions)))->all(), 'checked_at' => now()->toIso8601String()],
                'completed_at' => now(), 'error' => null,
            ]);

            $nextAttempt = $generation->opportunity_attempt + 1;
            $weekEnd = $generation->scheduled_for->copy()->endOfWeek()->toDateString();
            if ($generation->trigger !== 'scheduled' || $generation->usesAmp()
                || ($generation->engine ?? ContentPlan::ENGINE_COPILOT) !== ($plan->content_engine ?? ContentPlan::ENGINE_COPILOT) || $nextAttempt >= self::MAX_ATTEMPTS
                || (now($plan->timezone)->toDateString() > $weekEnd || now($plan->timezone)->toDateString() < $generation->scheduled_for->toDateString()) || app(ContentSchedule::class)->pauseReason($plan)
                || $plan->generations()->whereIn('status', [ContentGeneration::STATUS_PENDING, ContentGeneration::STATUS_RUNNING])->exists()) {
                return;
            }
            $rootId = $generation->opportunity_root_id ?? $generation->id;
            $candidate = new ContentGeneration([
                'content_plan_id' => $plan->id, 'website_repository_id' => $generation->website_repository_id,
                'requested_by' => $generation->requested_by, 'scheduled_for' => $generation->scheduled_for,
                'trigger' => 'scheduled', 'engine' => $generation->engine,
                'opportunity_root_id' => $rootId, 'opportunity_attempt' => $nextAttempt,
            ]);
            $candidate->setRelation('plan', $plan);
            $candidate->setRelation('repository', $generation->repository);
            if (app(ContentWorkSelector::class)->select($candidate)['requests']->isEmpty()) {
                return;
            }
            $next = $plan->generations()->firstOrCreate(
                ['scheduled_for' => $generation->scheduled_for->toDateString(), 'opportunity_attempt' => $nextAttempt],
                $candidate->getAttributes(),
            );
            if ($next->wasRecentlyCreated) {
                $generation->update(['skip_reason' => $generation->skip_reason.' Replacement attempt '.($nextAttempt + 1).'/'.self::MAX_ATTEMPTS.' queued.']);
                StartContentGeneration::dispatch($next)->afterCommit();
            }
        });
    }
}
