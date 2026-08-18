<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSeoProspectSearchRequest;
use App\Jobs\DiscoverSeoProspects;
use App\Models\SeoProspectSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SeoProspectSearchController extends Controller
{
    public function store(StoreSeoProspectSearchRequest $request): RedirectResponse
    {
        $search = SeoProspectSearch::query()->create(['user_id' => $request->user()->id, ...$request->validated()]);
        DiscoverSeoProspects::dispatch($search);

        return redirect()->route('admin.seo-prospect-searches.show', $search)->with('status', 'SEO opportunity search queued. No prospects have been added and no emails will be sent.');
    }

    public function show(SeoProspectSearch $seoProspectSearch): View
    {
        $seoProspectSearch->load(['candidates' => fn ($query) => $query->with(['rankings', 'prospect'])->orderByDesc('opportunity_score')->orderBy('domain')]);

        return view('admin.prospect-discoveries.seo-show', ['search' => $seoProspectSearch]);
    }
}
