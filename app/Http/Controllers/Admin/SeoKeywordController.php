<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoKeyword;
use App\Models\Website;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SeoKeywordController extends Controller
{
    public function show(Request $request, Website $website, SeoKeyword $seoKeyword): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        abort_unless($seoKeyword->website_id === $website->id, 404);

        $observations = $website->seoKeywords()
            ->where('keyword', $seoKeyword->keyword)
            ->where('location_code', $seoKeyword->location_code)
            ->where('language_code', $seoKeyword->language_code)
            ->with('snapshot:id,snapshot_date,status')
            ->whereHas('snapshot', fn ($query) => $query->whereIn('status', ['completed', 'completed_with_errors']))
            ->get()
            ->sortBy(fn (SeoKeyword $keyword): string => $keyword->snapshot->snapshot_date->toDateString())
            ->map(fn (SeoKeyword $keyword): array => [
                'snapshot_date' => $keyword->snapshot->snapshot_date->toDateString(),
                'position' => $keyword->position,
                'estimated_traffic' => (float) ($keyword->estimated_traffic ?? 0),
                'search_volume' => $keyword->search_volume ?? 0,
                'ranking_url' => $keyword->ranking_url,
            ])
            ->values();

        return view('admin.websites.seo-keyword', compact('website', 'seoKeyword', 'observations'));
    }
}
