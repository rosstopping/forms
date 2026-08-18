<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreSeoProspectSearchRequest;
use App\Jobs\DiscoverSeoProspects;
use App\Models\SeoProspectSearch;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoProspectSearchController extends Controller
{
    public function store(StoreSeoProspectSearchRequest $request): RedirectResponse
    {
        $search = SeoProspectSearch::query()->create(['user_id' => $request->user()->id, ...$request->validated()]);
        DiscoverSeoProspects::dispatch($search);

        return redirect()->route('admin.seo-prospect-searches.show', $search)->with('status', 'SEO opportunity search queued. No prospects have been added and no emails will be sent.');
    }

    public function show(Request $request, SeoProspectSearch $seoProspectSearch): View
    {
        $filters = $request->validate([
            'qualification' => ['nullable', 'in:suitable,too_large,unsuitable,analysis_failed,pending_analysis,analyzing'],
            'migration' => ['nullable', 'in:easy,medium,hard,unknown'],
            'minimum_score' => ['nullable', 'integer', 'min:0', 'max:100'],
        ]);
        $seoProspectSearch->load(['candidates' => fn ($query) => $query
            ->with(['rankings', 'prospect'])
            ->when($filters['qualification'] ?? null, fn ($query, string $status) => $query->where('qualification_status', $status))
            ->when($filters['migration'] ?? null, fn ($query, string $difficulty) => $query->where('migration_difficulty', $difficulty))
            ->when(isset($filters['minimum_score']), fn ($query) => $query->where('opportunity_score', '>=', $filters['minimum_score']))
            ->orderByDesc('opportunity_score')
            ->orderBy('domain')]);

        return view('admin.prospect-discoveries.seo-show', ['search' => $seoProspectSearch, 'filters' => $filters]);
    }
}
