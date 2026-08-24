<?php

namespace App\Http\Controllers;

use App\Models\ProspectOutreachLink;
use App\Services\ProspectEngagementSourceClassifier;
use App\Services\ProspectOutreachTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProspectOutreachClickController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, ProspectOutreachLink $link, ProspectOutreachTracker $tracker, ProspectEngagementSourceClassifier $sourceClassifier): RedirectResponse
    {
        $tracker->recordClick($link, $sourceClassifier->classify($request));

        return redirect()->away($link->destination_url);
    }
}
