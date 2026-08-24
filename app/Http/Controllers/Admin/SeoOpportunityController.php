<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SeoOpportunity;
use App\Models\Website;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SeoOpportunityController extends Controller
{
    public function queue(Request $request, Website $website, SeoOpportunity $seoOpportunity, ContentOpportunityQueuer $queuer): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($seoOpportunity->website_id === $website->id, 404);
        abort_unless($website->repository || ($request->user()->isAdmin() && config('forms.pixel_ui_enabled') && $website->pixel_enabled), 422, 'Connect GitHub or enable Pixel before adding an opportunity to the content queue.');
        abort_unless($seoOpportunity->status === SeoOpportunity::STATUS_OPEN, 422, 'Only open opportunities can be queued.');

        $queuer->queueSeo($seoOpportunity, $request->user());

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'seo'])
            ->with('status', 'SEO recommendation added to the content todos.');
    }
}
