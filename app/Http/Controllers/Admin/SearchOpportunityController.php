<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\DiscoverSearchOpportunities;
use App\Models\SearchOpportunity;
use App\Models\Website;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SearchOpportunityController extends Controller
{
    public function refresh(Request $request, Website $website): RedirectResponse
    {
        $this->authorizeWebsite($request, $website);
        $connection = $website->searchConsoleConnection()->whereNotNull('property_url')->firstOrFail();
        DiscoverSearchOpportunities::dispatch($connection);

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'search'])->with('status', 'Search opportunity analysis queued.');
    }

    public function queue(Request $request, Website $website, SearchOpportunity $searchOpportunity, ContentOpportunityQueuer $queuer): RedirectResponse
    {
        $this->authorizeOpportunity($request, $website, $searchOpportunity);
        abort_unless($website->repository, 422, 'Connect the website repository before sending an opportunity to Copilot.');
        abort_unless($searchOpportunity->status === SearchOpportunity::STATUS_OPEN, 422, 'Only open opportunities can be queued.');

        $queuer->queueSearch($searchOpportunity, $request->user());

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'search'])->with('status', 'Search opportunity added to the approval-first Copilot content queue.');
    }

    public function dismiss(Request $request, Website $website, SearchOpportunity $searchOpportunity): RedirectResponse
    {
        $this->authorizeOpportunity($request, $website, $searchOpportunity);
        abort_unless($searchOpportunity->status === SearchOpportunity::STATUS_OPEN, 422);
        $searchOpportunity->update(['status' => SearchOpportunity::STATUS_DISMISSED]);

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'search'])->with('status', 'Search opportunity dismissed.');
    }

    protected function authorizeOpportunity(Request $request, Website $website, SearchOpportunity $searchOpportunity): void
    {
        $this->authorizeWebsite($request, $website);
        abort_unless($searchOpportunity->website_id === $website->id, 404);
    }

    protected function authorizeWebsite(Request $request, Website $website): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
    }
}
