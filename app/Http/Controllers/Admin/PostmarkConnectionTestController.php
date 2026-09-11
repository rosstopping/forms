<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\PostmarkServerClient;
use Illuminate\Http\RedirectResponse;
use Throwable;

class PostmarkConnectionTestController extends Controller
{
    public function __invoke(Website $website, PostmarkServerClient $postmark): RedirectResponse
    {
        abort_unless($website->isManageableBy(request()->user()), 403);
        $connection = $website->mailConnection;
        abort_unless($connection && $connection->status === 'active' && filled($connection->postmark_server_token), 422);

        $fromEmail = $website->autoresponder_from_email ?: config('mail.from.address');
        $fromName = $website->autoresponder_from_name ?: config('mail.from.name');

        try {
            $postmark->send($connection->postmark_server_token, [
                'From' => $fromName.' <'.$fromEmail.'>',
                'To' => request()->user()->email,
                'Subject' => 'Sitewell email delivery test',
                'TextBody' => 'Your Postmark email delivery connection is working.',
                'MessageStream' => 'outbound',
                'Metadata' => ['website_id' => (string) $website->id, 'type' => 'connection_test'],
            ]);
        } catch (Throwable) {
            return back()->withErrors(['postmark_server_token' => 'The Postmark test email could not be sent. Check the token and From address.']);
        }

        return back()->with('status', 'A Postmark test email was sent to '.request()->user()->email.'.');
    }
}
