<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WordPressPairingCodeController extends Controller
{
    public function __invoke(Request $request, Website $website, WordPressConnectionManager $connections): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        if (! $website->repository()->exists()) {
            return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'content'])
                ->with('error', 'Connect the website GitHub repository before connecting WordPress.');
        }

        $pairing = $connections->issuePairingCode($website);

        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'content'])
            ->with('status', 'WordPress connection code created.')
            ->with('wordpress_pairing_code', $pairing['code'])
            ->with('wordpress_pairing_code_expires_at', $pairing['expires_at']->toIso8601String());
    }
}
