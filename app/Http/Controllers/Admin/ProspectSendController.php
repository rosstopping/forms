<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ProspectSendController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Prospect $prospect): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        abort_if($prospect->approved_at === null, 422, 'Approve this draft before sending.');
        abort_if($prospect->sent_at !== null, 422, 'This outreach email has already been sent.');
        abort_if($prospect->suppressed_at !== null, 422, 'This prospect is on the suppression list.');
        abort_if(blank($prospect->email), 422, 'Add an email address before sending.');
        abort_if(blank(config('services.sitewell.showcase_video_url')), 422, 'Configure the showcase video URL before sending.');

        Mail::to($prospect->email)->send(new ProspectOutreach($prospect));
        $prospect->update(['status' => 'contacted', 'sent_at' => now(), 'next_follow_up_at' => now()->addWeek()]);
        $prospect->recordActivity('sent', 'Approved outreach email sent.', $request->user());

        return back()->with('status', 'Outreach email sent. A follow-up reminder was scheduled for one week.');
    }
}
