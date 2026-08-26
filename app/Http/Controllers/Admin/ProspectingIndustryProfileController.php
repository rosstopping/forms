<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProspectEngagementEventType;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectingIndustryProfileRequest;
use App\Http\Requests\UpdateProspectingIndustryProfileRequest;
use App\Models\ProspectingIndustryProfile;
use App\Models\ProspectingLocation;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProspectingIndustryProfileController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(): View
    {
        $profiles = ProspectingIndustryProfile::query()
            ->withCount([
                'prospects',
                'prospects as approved_prospects_count' => fn ($query) => $query->whereNotNull('approved_at'),
                'prospects as emailed_prospects_count' => fn ($query) => $query->whereNotNull('sent_at'),
                'prospects as replied_prospects_count' => fn ($query) => $query->whereNotNull('replied_at'),
                'prospects as warm_prospects_count' => fn ($query) => $query->where('lead_temperature', 'warm'),
                'prospects as hot_prospects_count' => fn ($query) => $query->where('lead_temperature', 'hot'),
                'prospects as customers_count' => fn ($query) => $query->whereNotNull('converted_at'),
                'engagementEvents as opens_count' => fn ($query) => $query->where('event_type', ProspectEngagementEventType::EmailOpened),
                'engagementEvents as clicks_count' => fn ($query) => $query->whereIn('event_type', [ProspectEngagementEventType::AuditClicked, ProspectEngagementEventType::SitewellClicked, ProspectEngagementEventType::PersonalisedVideoClicked, ProspectEngagementEventType::BookingPageClicked]),
            ])
            ->orderByDesc('priority')
            ->orderBy('name')
            ->get();

        return view('admin.prospecting-strategy.index', [
            'profiles' => $profiles,
            'locations' => ProspectingLocation::query()->orderByDesc('priority')->orderBy('name')->get(),
        ]);
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create(): View
    {
        return view('admin.prospecting-strategy.industry-form', ['profile' => new ProspectingIndustryProfile]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreProspectingIndustryProfileRequest $request): RedirectResponse
    {
        ProspectingIndustryProfile::query()->create($request->validated());

        return redirect()->route('admin.prospecting-industry-profiles.index')->with('status', 'Prospecting industry created.');
    }

    /**
     * Display the specified resource.
     */
    public function edit(ProspectingIndustryProfile $prospectingIndustryProfile): View
    {
        return view('admin.prospecting-strategy.industry-form', ['profile' => $prospectingIndustryProfile]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateProspectingIndustryProfileRequest $request, ProspectingIndustryProfile $prospectingIndustryProfile): RedirectResponse
    {
        $prospectingIndustryProfile->update($request->validated());

        return redirect()->route('admin.prospecting-industry-profiles.index')->with('status', 'Prospecting industry updated.');
    }
}
