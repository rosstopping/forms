<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Prospect;
use App\Services\ProspectOutreachSender;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProspectSendController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Prospect $prospect, ProspectOutreachSender $sender): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        abort_if($eligibilityError = $sender->eligibilityError($prospect), 422, $eligibilityError);
        $sender->send($prospect, $request->user());

        return back()->with('status', $prospect->isAgencyPartner()
            ? 'Outreach email sent. One follow-up is planned after '.config('outreach.partner_follow_up_working_days', 6).' working days, subject to replies and engagement.'
            : 'Outreach email sent. A follow-up reminder was scheduled for one week.');
    }
}
