<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use App\Models\Website;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ContentSuggestionController extends Controller
{
    public function __invoke(Request $request, Website $website, ContentOpportunityQueuer $queuer): View
    {
        abort_unless($website->repository || (config('forms.pixel_ui_enabled') && $website->pixel_enabled), 422, 'Connect GitHub or enable Pixel before adding an opportunity.');

        $creator = $website->contentPlan()->firstOrFail()->creator()->firstOrFail();

        $type = $request->string('type')->toString();
        $id = $request->integer('opportunity');
        if ($type === 'search') {
            $opportunity = SearchOpportunity::query()->whereBelongsTo($website)->findOrFail($id);
            abort_unless($opportunity->status === SearchOpportunity::STATUS_OPEN, 422, 'This suggestion is no longer available.');
            $queuer->queueSearch($opportunity, $creator);
        } elseif ($type === 'seo') {
            $opportunity = SeoOpportunity::query()->whereBelongsTo($website)->with('keyword')->findOrFail($id);
            abort_unless($opportunity->status === SeoOpportunity::STATUS_OPEN, 422, 'This suggestion is no longer available.');
            $queuer->queueSeo($opportunity, $creator);
        } else {
            abort(404);
        }

        return view('content-suggestion-queued', ['website' => $website]);
    }
}
