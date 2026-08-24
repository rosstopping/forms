<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Services\ProspectLifecycleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProspectApprovalController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Prospect $prospect, ProspectLifecycleManager $lifecycleManager): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        abort_if(blank($prospect->outreach_subject) || blank($prospect->outreach_body), 422, 'Create an outreach draft before approving it.');
        $prospect->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $request->user()->id]);
        $lifecycleManager->markApproved($prospect, $request->user());
        $prospect->recordActivity('approved', 'Outreach email approved.', $request->user());

        return back()->with('status', 'Outreach approved and ready to send.');
    }
}
