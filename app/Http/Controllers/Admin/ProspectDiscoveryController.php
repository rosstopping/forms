<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreProspectDiscoveryRequest;
use App\Jobs\DiscoverProspects;
use App\Models\ProspectDiscovery;
use App\Models\SeoProspectSearch;
use App\Services\OpenStreetMapProspectFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ProspectDiscoveryController extends Controller
{
    public function index(): View
    {
        $discoveries = ProspectDiscovery::query()->withCount('candidates')->latest()->paginate(12, pageName: 'local_page');
        $seoSearches = SeoProspectSearch::query()->withCount('candidates')->latest()->paginate(12, pageName: 'seo_page');

        return view('admin.prospect-discoveries.index', [
            'discoveries' => $discoveries,
            'seoSearches' => $seoSearches,
            'businessTypes' => OpenStreetMapProspectFinder::BUSINESS_TYPES,
            'dataForSeoConfigured' => filled(config('services.dataforseo.login')) && filled(config('services.dataforseo.password')),
            'serpLiveCostPerTen' => (float) config('services.dataforseo.serp_live_cost_per_ten'),
            'serpCacheDays' => (int) config('services.dataforseo.serp_cache_days'),
        ]);
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
