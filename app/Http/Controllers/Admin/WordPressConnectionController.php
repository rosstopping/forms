<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WordPressConnectionController extends Controller
{
    public function __invoke(Request $request, Website $website, WordPressConnectionManager $connections): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        if ($website->wordpressConnection) {
            $connections->revoke($website->wordpressConnection);
        }

        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'wordpress'])
            ->with('status', 'WordPress connection revoked.');
    }
}
