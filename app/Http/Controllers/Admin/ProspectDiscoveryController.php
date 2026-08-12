<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectDiscoveryRequest;
use App\Jobs\DiscoverProspects;
use App\Models\ProspectDiscovery;
use App\Services\OpenStreetMapProspectFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProspectDiscoveryController extends Controller
{
    public function index(): View
    {
        $discoveries = ProspectDiscovery::query()->withCount('candidates')->latest()->paginate(12);

        return view('admin.prospect-discoveries.index', ['discoveries' => $discoveries, 'businessTypes' => OpenStreetMapProspectFinder::BUSINESS_TYPES]);
    }

    public function store(StoreProspectDiscoveryRequest $request): RedirectResponse
    {
        $discovery = $request->user()->prospectDiscoveries()->create($request->validated());
        DiscoverProspects::dispatch($discovery);

        return redirect()->route('admin.prospect-discoveries.show', $discovery)->with('status', 'Prospect search queued. This normally takes a minute or two.');
    }

    public function show(ProspectDiscovery $prospectDiscovery): View
    {
        return view('admin.prospect-discoveries.show', ['discovery' => $prospectDiscovery->load(['candidates.prospect'])]);
    }
}
