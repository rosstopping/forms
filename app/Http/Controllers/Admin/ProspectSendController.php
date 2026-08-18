<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use App\Services\ProspectOutreachTracker;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Throwable;

class ProspectSendController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Prospect $prospect, ProspectOutreachTracker $tracker): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        abort_if($prospect->approved_at === null, 422, 'Approve this draft before sending.');
        abort_if($prospect->sent_at !== null, 422, 'This outreach email has already been sent.');
        abort_if($prospect->suppressed_at !== null, 422, 'This prospect is on the suppression list.');
        abort_if(blank($prospect->email), 422, 'Add an email address before sending.');
        abort_if(blank($prospect->website_url) && blank($prospect->showcase_video_url), 422, 'Add this prospect\'s showcase video URL before sending.');

        $delivery = $tracker->createDelivery($prospect);

        try {
            Mail::to($prospect->email)->send(new ProspectOutreach($prospect, $delivery));
        } catch (Throwable $exception) {
            $delivery->delete();

            throw $exception;
        }

        $delivery->update(['sent_at' => now()]);
        $prospect->update(['status' => 'contacted', 'sent_at' => now(), 'next_follow_up_at' => now()->addWeek()]);
        $prospect->recordActivity('sent', 'Approved outreach email sent.', $request->user());

        return back()->with('status', 'Outreach email sent. A follow-up reminder was scheduled for one week.');
    }
}
