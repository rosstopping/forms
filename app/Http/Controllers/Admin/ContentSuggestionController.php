<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SearchOpportunity;
use App\Models\SeoOpportunity;
use App\Models\Website;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class ContentSuggestionController extends Controller
{
    public function __invoke(Request $request, Website $website, ContentOpportunityQueuer $queuer): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($website->repository, 422, 'Connect the website repository before adding an opportunity.');

        $type = $request->string('type')->toString();
        $id = $request->integer('opportunity');
        if ($type === 'search') {
            $opportunity = SearchOpportunity::query()->whereBelongsTo($website)->findOrFail($id);
            abort_unless($opportunity->status === SearchOpportunity::STATUS_OPEN, 422, 'This suggestion is no longer available.');
            $queuer->queueSearch($opportunity, $request->user());
        } elseif ($type === 'seo') {
            $opportunity = SeoOpportunity::query()->whereBelongsTo($website)->with('keyword')->findOrFail($id);
            abort_unless($opportunity->status === SeoOpportunity::STATUS_OPEN, 422, 'This suggestion is no longer available.');
            $queuer->queueSeo($opportunity, $request->user());
        } else {
            abort(404);
        }

        return Redirect::route('admin.websites.show', $website)->with('status', 'Suggestion added to the next content run.');
    }
}
