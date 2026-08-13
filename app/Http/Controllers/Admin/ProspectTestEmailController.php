<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\ProspectOutreach;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;

class ProspectTestEmailController extends Controller
{
    public function __invoke(Request $request, Prospect $prospect): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin() && $prospect->isAccessibleBy($request->user()), 403);
        abort_if(blank($prospect->outreach_subject) || blank($prospect->outreach_body), 422, 'Save the outreach draft before sending a test.');
        abort_if(blank(config('services.sitewell.showcase_video_url')), 422, 'Configure the showcase video URL before sending a test.');

        Mail::to($request->user()->email)->send(new ProspectOutreach($prospect));
        $prospect->recordActivity('test_email_sent', 'Outreach test email sent to '.$request->user()->email.'.', $request->user());

        return back()->with('status', 'Test email sent to '.$request->user()->email.'.');
    }
}
