<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProspectLifecycleState;
use App\Enums\ProspectOutreachMessageType;
use App\Enums\ProspectOutreachStopReason;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectRequest;
use App\Http\Requests\UpdateProspectRequest;
use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\ProspectOutreachState;
use App\Services\LoomVideoThumbnail;
use App\Services\ProspectLifecycleManager;
use App\Services\ProspectPersonalisedVideo;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProspectController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request): View
    {
        $query = Prospect::query()->accessibleTo($request->user());
        $summary = (clone $query)->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status');
        $temperatureSummary = (clone $query)->selectRaw('lead_temperature, count(*) as total')->groupBy('lead_temperature')->pluck('total', 'lead_temperature');
        $hotVideoQuery = Prospect::query()
            ->accessibleTo($request->user())
            ->whereHas('outreachState', fn ($query) => $query
                ->whereIn('lifecycle_state', [ProspectLifecycleState::Hot, ProspectLifecycleState::NeedsPersonalisedVideo])
                ->whereNull('video_sent_at'));
        $hotVideoProspectsCount = (clone $hotVideoQuery)->count();
        $hotVideoProspects = $hotVideoQuery
            ->with([
                'outreachState',
                'engagementEvents' => fn ($query) => $query->where('score_delta', '>', 0)->latest('occurred_at')->limit(3),
                'outreachDeliveries' => fn ($query) => $query->with('links')->latest('sent_at')->limit(1),
            ])
            ->orderByDesc(ProspectOutreachState::query()
                ->select('engagement_score')
                ->whereColumn((new ProspectOutreachState)->qualifyColumn('prospect_id'), (new Prospect)->qualifyColumn('id'))
                ->limit(1))
            ->limit(12)
            ->get();
        $manualFollowUpQuery = Prospect::query()
            ->accessibleTo($request->user())
            ->whereHas('outreachState', fn ($query) => $query->whereNotNull('manual_follow_up_required_at'));
        $manualFollowUpProspectsCount = (clone $manualFollowUpQuery)->count();
        $manualFollowUpProspects = $manualFollowUpQuery
            ->with('outreachState')
            ->orderByDesc(ProspectOutreachState::query()
                ->select('manual_follow_up_required_at')
                ->whereColumn((new ProspectOutreachState)->qualifyColumn('prospect_id'), (new Prospect)->qualifyColumn('id'))
                ->limit(1))
            ->limit(12)
            ->get();
        $temperature = $request->string('temperature')->toString();
        $query->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when(in_array($temperature, Prospect::LEAD_TEMPERATURES, true), fn ($query) => $query->where('lead_temperature', $temperature))
            ->when($request->filled('search'), fn ($query) => $query->where(fn ($query) => $query->where('business_name', 'like', '%'.$request->string('search').'%')->orWhere('email', 'like', '%'.$request->string('search').'%')));
        $matchingProspectsCount = (clone $query)->count();
        $prospects = $query
            ->orderByRaw("case lead_temperature when 'hot' then 1 when 'warm' then 2 else 3 end")
            ->latest()->paginate(20)->withQueryString();

        return view('admin.prospects.index', compact('prospects', 'summary', 'temperatureSummary', 'matchingProspectsCount', 'hotVideoProspects', 'hotVideoProspectsCount', 'manualFollowUpProspects', 'manualFollowUpProspectsCount'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.prospects.create');
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProspectRequest $request, LoomVideoThumbnail $loomVideoThumbnail): RedirectResponse
    {
        $data = $request->validated();
        $data['website_url'] = filled($data['website_url'] ?? null) ? rtrim($data['website_url'], '/') : null;
        $data['showcase_video_thumbnail_url'] = $loomVideoThumbnail->fetch($data['showcase_video_url'] ?? null);
        if (! $data['website_url']) {
            $data = array_merge($data, $this->websiteOpportunityDraft($data['business_name']));
        }
        $prospect = $request->user()->prospects()->create($data);
        $prospect->recordActivity('created', $prospect->website_url ? 'Prospect added and queued for research.' : 'Prospect added as a website opportunity; website research skipped.', $request->user());

        if ($prospect->website_url) {
            AnalyzeProspect::dispatch($prospect);
        }

        return redirect()->route('admin.prospects.show', $prospect)->with('status', $prospect->website_url ? 'Prospect added. Website research has been queued.' : 'Prospect added as a website opportunity. No research or email has been sent.');
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Prospect $prospect, ProspectPersonalisedVideo $personalisedVideo): View
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);

        $prospect->load(['activities.user', 'outreachDeliveries.links', 'outreachState', 'engagementEvents']);
        $needsPersonalisedVideo = $prospect->outreachState->video_sent_at === null
            && ($prospect->lead_temperature === 'hot' || in_array($prospect->outreachState->lifecycle_state, [ProspectLifecycleState::Hot, ProspectLifecycleState::NeedsPersonalisedVideo], true));
        $scheduledVideoDelivery = $prospect->outreachDeliveries
            ->first(fn ($delivery) => $delivery->message_type === ProspectOutreachMessageType::PersonalisedVideo && $delivery->status === 'scheduled');

        return view('admin.prospects.show', [
            'prospect' => $prospect,
            'isFreeSiteAudit' => $prospect->activities->contains('type', 'free_audit_requested'),
            'needsPersonalisedVideo' => $needsPersonalisedVideo,
            'scheduledVideoDelivery' => $scheduledVideoDelivery,
            'personalisedVideoSubject' => $personalisedVideo->defaultSubject($prospect),
            'personalisedVideoBody' => $personalisedVideo->defaultBody($prospect),
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Prospect $prospect)
    {
        return redirect()->route('admin.prospects.show', $prospect);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProspectRequest $request, Prospect $prospect, LoomVideoThumbnail $loomVideoThumbnail, ProspectLifecycleManager $lifecycleManager): RedirectResponse
    {
        $previousStatus = $prospect->status;
        $data = $request->validated();
        $data['suppressed_at'] = $request->boolean('suppressed') ? ($prospect->suppressed_at ?: now()) : null;
        if ($data['suppressed_at']) {
            $data['scheduled_send_at'] = null;
        }
        unset($data['suppressed']);
        $draftChanged = $prospect->outreach_subject !== ($data['outreach_subject'] ?? null)
            || $prospect->outreach_body !== ($data['outreach_body'] ?? null)
            || $prospect->showcase_video_url !== ($data['showcase_video_url'] ?? null);
        $data['showcase_video_thumbnail_url'] = $loomVideoThumbnail->fetch($data['showcase_video_url'] ?? null);
        if ($draftChanged) {
            $data['approved_at'] = null;
            $data['approved_by'] = null;
            $data['scheduled_send_at'] = null;
            $data['status'] = 'drafted';
        }
        $prospect->update($data);
        if ($draftChanged) {
            $lifecycleManager->markQualified($prospect);
        } elseif ($data['suppressed_at']) {
            $lifecycleManager->stop($prospect, $request->user(), ProspectOutreachStopReason::Suppressed);
        } elseif ($previousStatus !== $prospect->status && $lifecycleState = $this->lifecycleStateForStatus($prospect->status)) {
            $lifecycleManager->transitionManually($prospect, $lifecycleState, $request->user());
        }
        $prospect->recordActivity('updated', $draftChanged ? 'Prospect and outreach draft updated; approval reset.' : 'Prospect updated.', $request->user());

        return back()->with('status', 'Prospect updated.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Prospect $prospect): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        $prospect->delete();

        return redirect()->route('admin.prospects.index')->with('status', 'Prospect deleted.');
    }

    /** @return array{analysis_status: string, status: string, outreach_subject: string, outreach_body: string} */
    protected function websiteOpportunityDraft(string $businessName): array
    {
        return [
            'analysis_status' => 'skipped',
            'status' => 'drafted',
            'outreach_subject' => 'Quick one for '.$businessName,
            'outreach_body' => "Hi there,\n\nI came across {$businessName} and couldn't see a website linked from the business listing. Thought Sitewell might be handy if you need one.\n\nI've included a quick video below so you can see what it does. If it looks useful, just reply and we can have a chat.\n\nCheers",
        ];
    }

    private function lifecycleStateForStatus(string $status): ?ProspectLifecycleState
    {
        return match ($status) {
            'new' => ProspectLifecycleState::New,
            'researched', 'drafted' => ProspectLifecycleState::Qualified,
            'approved' => ProspectLifecycleState::Approved,
            'contacted' => ProspectLifecycleState::InitialEmailSent,
            'replied' => ProspectLifecycleState::Replied,
            'converted' => ProspectLifecycleState::Customer,
            'not_interested' => ProspectLifecycleState::NotInterested,
            default => null,
        };
    }
}
