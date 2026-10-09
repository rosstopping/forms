<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreContentRequestRequest;
use App\Http\Requests\UpdateContentRequestQueueRequest;
use App\Jobs\GenerateContentRequestPixelOptimisations;
use App\Models\BacklinkOpportunity;
use App\Models\CompetitorOpportunity;
use App\Models\ContentGeneration;
use App\Models\ContentOpportunity;
use App\Models\ContentRequest;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use App\Models\Website;
use App\Services\SeoImpactTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class ContentRequestController extends Controller
{
    public function store(StoreContentRequestRequest $request, Website $website): RedirectResponse
    {
        $contentRequest = $website->contentRequests()->create([
            ...$request->validated(),
            'created_by' => $request->user()->id,
        ]);

        app(SeoImpactTracker::class)->forRequest($contentRequest);

        if (config('forms.pixel_ui_enabled') && $website->pixel_enabled) {
            GenerateContentRequestPixelOptimisations::dispatch($contentRequest, $request->user());
        }

        return Redirect::route('admin.websites.section', [$website, 'content'])->with('status', 'Content request added for the next generation.');
    }

    public function destroy(Request $request, Website $website, ContentRequest $contentRequest): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($contentRequest->website_id === $website->id, 404);
        abort_if($contentRequest->budget_reserved_at, 422, 'This request has reserved content usage. Hold it instead of deleting its usage history.');
        abort_if($contentRequest->picked_up_at, 422, 'A content request cannot be removed after generation has started.');
        abort_if(in_array($contentRequest->seoImpact?->status, ['measuring', 'review_required'], true), 422, 'Review this change’s SEO impact before removing its content request.');

        DB::transaction(function () use ($contentRequest): void {
            Website::whereKey($contentRequest->website_id)->lockForUpdate()->firstOrFail();
            $contentRequest->refresh();
            abort_if($contentRequest->budget_reserved_at || $contentRequest->picked_up_at, 422, 'Work has started or usage has been reserved. Hold the request instead.');
            $contentRequest->seoImpact?->update(['status' => 'cancelled', 'next_measurement_at' => null]);
            foreach ([SearchOpportunity::class, SeoOpportunity::class, CompetitorOpportunity::class, BacklinkOpportunity::class, ContentOpportunity::class] as $model) {
                $model::where('website_id', $contentRequest->website_id)->where('content_request_id', $contentRequest->id)->update(['status' => 'open', 'content_request_id' => null]);
            }
            $contentRequest->delete();
        });

        return Redirect::route('admin.websites.section', [$website, 'content'])->with('status', 'Content request removed.');
    }

    public function updateQueue(UpdateContentRequestQueueRequest $request, Website $website, ContentRequest $contentRequest): RedirectResponse
    {
        abort_unless($contentRequest->website_id === $website->id, 404);
        $updated = Cache::lock('content-request-work-'.$contentRequest->id, 180)->get(function () use ($request, $website, $contentRequest): bool {
            DB::transaction(function () use ($request, $website, $contentRequest): void {
                Website::whereKey($website->id)->lockForUpdate()->firstOrFail();
                $plan = $website->contentPlan()->lockForUpdate()->first();
                abort_if($plan?->generations()->where('status', ContentGeneration::STATUS_RUNNING)->exists(), 422, 'Wait for the running content task to finish.');
                $contentRequest->refresh();
                abort_if($contentRequest->picked_up_at || $contentRequest->optimisations()->exists(), 422, 'Queue controls only apply before work has started.');
                $data = $request->validated();
                abort_if($data['action'] === 'classify' && $contentRequest->budget_reserved_at, 422, 'The work classification is fixed once content usage has been reserved.');
                $attributes = match ($data['action']) {
                    'plan' => ['planning_status' => 'planned', 'planned_for' => $data['planned_for'] ?? null, 'preflight' => null],
                    'enqueue' => ['planning_status' => 'queued', 'preflight' => null],
                    'classify' => ['work_type' => $data['work_type'], 'preflight' => null],
                    'hold' => ['held_at' => now(), 'hold_reason' => $data['hold_reason']],
                    'release' => ['held_at' => null, 'hold_reason' => null],
                    'dependencies' => ['dependencies' => ['urls' => $data['urls'] ?? [], 'files' => $data['files'] ?? ($contentRequest->dependencies['files'] ?? [])], 'preflight' => null],
                };
                $contentRequest->update($attributes);
            });

            return true;
        });
        abort_if($updated === false, 422, 'Pixel is currently preparing this request. Wait for it to finish.');
        if (in_array($request->input('action'), ['release', 'enqueue'], true) && config('forms.pixel_ui_enabled') && $website->pixel_enabled) {
            GenerateContentRequestPixelOptimisations::dispatch($contentRequest, $request->user());
        }

        return Redirect::route('admin.websites.section', [$website, 'content', 'content_section' => 'queue'])->with('status', 'Content queue controls updated.');
    }

    public function bump(Request $request, Website $website, ContentRequest $contentRequest): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($contentRequest->website_id === $website->id, 404);
        abort_if($contentRequest->picked_up_at, 422, 'A content request cannot be reordered after generation has started.');

        $contentRequest->update(['bumped_at' => now()]);

        return Redirect::route('admin.websites.section', [$website, 'content'])->with('status', 'Content request moved to the top of the queue.');
    }
}
