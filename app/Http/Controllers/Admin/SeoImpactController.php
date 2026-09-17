<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ConfirmSeoImpactLiveRequest;
use App\Http\Requests\ReviewSeoImpactRequest;
use App\Http\Requests\UpdateSeoImpactRequest;
use App\Models\SeoImpact;
use App\Models\Website;
use App\Services\SeoImpactTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redirect;

class SeoImpactController extends Controller
{
    public function index(Request $request, Website $website): RedirectResponse
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);

        return Redirect::route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact']);
    }

    public function show(Request $request, Website $website, SeoImpact $seoImpact): RedirectResponse
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        abort_unless($seoImpact->website_id === $website->id, 404);

        return Redirect::route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $seoImpact->id]);
    }

    public function update(UpdateSeoImpactRequest $request, Website $website, SeoImpact $seoImpact): RedirectResponse
    {
        DB::transaction(function () use ($request, $seoImpact): void {
            $impact = SeoImpact::lockForUpdate()->findOrFail($seoImpact->id);
            abort_unless($impact->status === 'planned', 422, 'The measurement brief is frozen after live confirmation.');
            $impact->update([...$request->validated(), 'baseline' => null, 'property_url' => null, 'next_measurement_at' => now(), 'measurement_error' => null]);
        });

        return Redirect::route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $seoImpact->id])->with('status', 'Brief saved. Its Search Console baseline will be collected automatically.');
    }

    public function confirmLive(ConfirmSeoImpactLiveRequest $request, Website $website, SeoImpact $seoImpact): RedirectResponse
    {
        DB::transaction(function () use ($request, $website, $seoImpact): void {
            $impact = SeoImpact::lockForUpdate()->findOrFail($seoImpact->id);
            abort_unless($impact->status === 'planned', 422, 'This change has already been confirmed or closed.');
            abort_if($impact->target_urls === [], 422, 'Save the affected canonical page URLs before confirming delivery.');
            abort_unless(app(SeoImpactTracker::class)->websiteUrls($website, $impact->target_urls) === $impact->target_urls, 422, 'The target pages must belong to this website. Update the brief before confirming delivery.');
            $data = $request->validated();
            $impact->update([
                'live_at' => Carbon::parse($data['live_date'], 'America/Los_Angeles')->utc(), 'status' => 'measuring',
                'actual_changes' => $data['actual_changes'], 'deployment_evidence' => $data['deployment_evidence'], 'confirmed_by' => $request->user()->id,
                'property_url' => $website->searchConsoleConnection?->property_url, 'baseline' => null, 'observations' => null,
                'next_measurement_at' => now(), 'measurement_error' => null,
            ]);
        });

        return Redirect::route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $seoImpact->id])->with('status', 'Live date recorded. The affected pages are protected while results are measured.');
    }

    public function review(ReviewSeoImpactRequest $request, Website $website, SeoImpact $seoImpact, SeoImpactTracker $tracker): RedirectResponse
    {
        DB::transaction(function () use ($request, $website, $seoImpact, $tracker): void {
            $impact = SeoImpact::lockForUpdate()->findOrFail($seoImpact->id);
            abort_if(in_array($impact->status, ['completed', 'cancelled'], true), 422, 'This review has already been closed.');
            $data = $request->validated();
            if ($data['decision'] === 'extend') {
                abort_unless($impact->status === 'review_required' && $impact->review_after_days < 168, 422, 'Extension is available after the final checkpoint, up to 168 days.');
                $impact->update([...$data, 'status' => 'measuring', 'review_after_days' => $impact->review_after_days + 28, 'next_measurement_at' => now(), 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);

                return;
            }
            abort_unless($data['decision'] === 'cancel' || $impact->live_at, 422, 'Confirm delivery before reviewing results.');
            $impact->update([...$data, 'status' => $data['decision'] === 'cancel' ? 'cancelled' : 'completed', 'next_measurement_at' => null, 'reviewed_by' => $request->user()->id, 'reviewed_at' => now()]);
            if (in_array($data['decision'], ['iterate', 'investigate', 'rollback'], true)) {
                $followup = $website->contentRequests()->create(['created_by' => $request->user()->id,
                    'instructions' => 'Review SEO impact #'.$impact->id.': '.$impact->title."\nObserved outcome: ".($impact->outcome ?? 'Not measured')." (not proof of causation).\nRequested next step: ".$data['decision']."\n".$data['decision_notes']."\nAffected pages: ".implode(', ', $impact->target_urls)."\nInspect the evidence before proposing a focused change for human review. Do not automatically publish or roll back.",
                ]);
                $tracker->forRequest($followup)->update([
                    'target_urls' => $impact->target_urls, 'target_queries' => $impact->target_queries, 'primary_metric' => $impact->primary_metric,
                    'country' => $impact->country, 'device' => $impact->device, 'hypothesis' => $data['decision_notes'],
                    'evidence' => ['source' => 'impact_review', 'seo_impact_id' => $impact->id, 'outcome' => $impact->outcome], 'next_measurement_at' => now(),
                ]);
            }
        });

        return Redirect::route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $seoImpact->id])->with('status', 'Decision saved. Any requested follow-up is in the content queue for review.');
    }
}
