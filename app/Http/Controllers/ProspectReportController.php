<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use App\Models\ProspectOutreachLink;
use App\Services\ProspectEngagementSourceClassifier;
use App\Services\ProspectOutreachTracker;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProspectReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Prospect $prospect, ProspectOutreachTracker $tracker, ProspectEngagementSourceClassifier $sourceClassifier): View
    {
        if ($request->filled('outreach_link')) {
            $outreachLink = ProspectOutreachLink::query()
                ->with('delivery.prospect')
                ->where('uuid', $request->string('outreach_link'))
                ->where('kind', 'website_audit')
                ->whereHas('delivery', fn ($query) => $query->whereBelongsTo($prospect))
                ->first();

            if ($outreachLink) {
                $tracker->recordReportVisit($outreachLink, $sourceClassifier->classify($request));
            }
        }

        return view('prospects.report', compact('prospect'));
    }
}
