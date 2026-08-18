<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\RerunSeoProspectSearchRequest;
use App\Jobs\DiscoverSeoProspects;
use App\Models\SeoProspectSearch;
use App\Services\CachedSerpProvider;
use App\Services\SeoProspectCostEstimator;
use Illuminate\Http\RedirectResponse;

class RerunSeoProspectSearchController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(RerunSeoProspectSearchRequest $request, SeoProspectSearch $seoProspectSearch, CachedSerpProvider $cache, SeoProspectCostEstimator $costEstimator): RedirectResponse
    {
        abort_if(in_array($seoProspectSearch->status, ['pending', 'running', 'analyzing'], true), 422, 'Wait for this search to finish before running it again.');

        if ($request->boolean('refresh_serps')) {
            foreach ($seoProspectSearch->keywords as $keyword) {
                $cache->forget($keyword, $seoProspectSearch->location, $seoProspectSearch->maximum_position);
            }
        }

        $rerun = SeoProspectSearch::query()->create([
            'user_id' => $request->user()->id,
            'rerun_of_id' => $seoProspectSearch->rerun_of_id ?: $seoProspectSearch->id,
            'industry' => $seoProspectSearch->industry,
            'location' => $seoProspectSearch->location,
            'service_keywords' => $seoProspectSearch->service_keywords,
            'keywords' => $seoProspectSearch->keywords,
            'minimum_position' => $seoProspectSearch->minimum_position,
            'maximum_position' => $seoProspectSearch->maximum_position,
            'maximum_pages' => $seoProspectSearch->maximum_pages,
            'provider' => $seoProspectSearch->provider,
            'estimated_api_cost' => $costEstimator->estimate($seoProspectSearch->keywords, $seoProspectSearch->maximum_position),
        ]);
        DiscoverSeoProspects::dispatch($rerun);

        $message = $request->boolean('refresh_serps')
            ? 'Fresh SEO opportunity rerun queued. Cached SERPs were cleared, so provider charges may apply.'
            : 'SEO opportunity rerun queued. SERPs from the last seven days will be reused where available.';

        return redirect()->route('admin.seo-prospect-searches.show', $rerun)->with('status', $message);
    }
}
