<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\WebsiteProspectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteProspectController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Website $website, WebsiteProspectService $websiteProspects): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && $website->isAccessibleBy($request->user()), 403);

        $prospect = $websiteProspects->createOrLink($website, $request->user());

        return redirect()->route('admin.prospects.show', $prospect)
            ->with('status', $prospect->wasRecentlyCreated ? 'Outreach prospect created. Website research has been queued.' : 'This website is already linked to an outreach prospect.');
    }
}
