<?php

namespace App\Services;

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class ManualContentRequest
{
    public function __construct(private ContentGenerationPromptGenerator $prompts, private SeoTargetKeywordSelector $targets) {}

    public function prompt(ContentRequest $request): string
    {
        if ($request->manual_prompt) {
            return $request->manual_prompt;
        }

        $request->loadMissing(['website.contentPlan', 'website.repository', 'website.domains', 'seoImpact']);
        $website = $request->website;
        $plan = $website->contentPlan ?? new ContentPlan(['website_id' => $website->id]);
        $plan->setRelation('website', $website);
        $performanceSnapshot = $plan->exists ? $plan->generations()->whereNotNull('search_performance')
            ->where('started_at', '>=', now()->subDays(28))->latest('started_at')->latest('id')
            ->first(['id', 'search_performance', 'started_at']) : null;
        $generation = new ContentGeneration([
            'content_plan_id' => $plan->id,
            'target_keyword_context' => $this->targets->snapshot($this->targets->active($website)),
            'competitor_context' => $request->competitor_context ? [$request->competitor_context] : [],
            'backlink_context' => $request->backlink_context ? [$request->backlink_context] : [],
            'search_performance' => $performanceSnapshot?->search_performance ?? [],
        ]);
        $generation->setRelation('plan', $plan);
        $generation->setRelation('repository', $website->repository ?? new WebsiteRepository);
        $generation->setRelation('contentRequests', new Collection([$request]));
        $generation->setRelation('targetKeyword', null);

        return "Manual content task for your AI coding assistant. Prepare a reviewable change; do not publish or merge automatically.\n"
            .'Website domain: '.($website->primaryDomain()?->domain ?? 'Confirm the canonical website domain from the repository.')."\n"
            .'Search Console snapshot: '.($performanceSnapshot?->started_at?->toIso8601String() ?? 'No recent saved snapshot is available.')."\n"
            ."This brief uses saved Sitewell evidence; no fresh Search Console data was fetched. Inspect the current repository and open reviews before editing.\n\n"
            .$this->prompts->generate($generation);
    }

    public function take(ContentRequest $request, User $admin): void
    {
        $taken = Cache::lock('content-request-work-'.$request->id, 180)->get(function () use ($request, $admin): bool {
            DB::transaction(function () use ($request, $admin): void {
                Website::query()->whereKey($request->website_id)->lockForUpdate()->firstOrFail();
                $plan = ContentPlan::where('website_id', $request->website_id)->lockForUpdate()->first();
                abort_if($plan?->generations()->where('status', ContentGeneration::STATUS_RUNNING)->exists(), 422, 'Wait for the running content task to finish before taking manual work.');
                $request->refresh();
                abort_if(($request->planning_status ?? 'queued') !== 'queued', 422, 'Approve planned content into the queue before taking manual work.');
                if ($plan) {
                    $strategyReason = app(ContentStrategy::class)->requestPauseReason($plan, $request);
                    abort_if($strategyReason !== null, 422, $strategyReason ?? 'Work is paused by content strategy.');
                }
                abort_if($request->held_at, 422, 'Release this request from hold before taking manual work.');
                abort_if($request->picked_up_at, 422, 'This request has already been picked up.');
                abort_if($request->optimisations()->exists(), 422, 'Review or remove the existing Pixel changes before taking this request for manual work.');

                if ($plan) {
                    $state = app(ContentWorkSelector::class)->queueStates($plan, includeRepository: false, copilot: false)[$request->id] ?? null;
                    abort_if($state && $state['state'] !== 'ready', 422, $state['reason'] ?? 'This request is not eligible for preparation.');
                }
                $budgetReason = app(ContentBudget::class)->reserveRequest($request);
                abort_if($budgetReason !== null, 422, $budgetReason ?? 'Monthly content limit reached.');

                app(SeoImpactTracker::class)->forRequest($request);
                $request->unsetRelation('seoImpact');
                $request->update([
                    'manual_taken_by' => $admin->id,
                    'manual_started_at' => now(),
                    'picked_up_at' => now(),
                    'manual_prompt' => $this->prompt($request),
                ]);
            });

            return true;
        });
        abort_if($taken === false, 422, 'Pixel is currently preparing this request. Wait for it to finish before taking manual work.');
    }

    public function returnToQueue(ContentRequest $request): void
    {
        $updated = ContentRequest::whereKey($request->id)->whereNotNull('manual_started_at')->whereNull('manual_completed_at')
            ->update(['manual_taken_by' => null, 'manual_started_at' => null, 'manual_prompt' => null, 'picked_up_at' => null]);
        abort_unless($updated, 422, 'Only active manual work can be returned to the queue.');
    }

    public function complete(ContentRequest $request): void
    {
        $updated = ContentRequest::whereKey($request->id)->whereNotNull('manual_started_at')->whereNull('manual_completed_at')
            ->update(['manual_completed_at' => now()]);
        abort_unless($updated, 422, 'Only active manual work can be marked complete.');
    }
}
