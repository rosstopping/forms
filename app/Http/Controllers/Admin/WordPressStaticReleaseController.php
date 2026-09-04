<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\WordPressStaticReleaseQueuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WordPressStaticReleaseController extends Controller
{
    public function __invoke(Request $request, Website $website, WordPressStaticReleaseQueuer $releases): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        if (! $website->repository()->exists()) {
            return $this->redirect($website)->with('error', 'Connect a GitHub repository before creating a static release.');
        }

        if (! $website->wordpressConnection?->isConnected()) {
            return $this->redirect($website)->with('error', 'Connect the WordPress plugin before creating a static release.');
        }

        $result = $releases->queue($website, $request->user()->id);

        if (! $result['created']) {
            return $this->redirect($website)->with('status', 'A WordPress static release is already being prepared.');
        }

        return $this->redirect($website)->with('status', 'The static release has been queued. WordPress will install it automatically when it is ready.');
    }

    private function redirect(Website $website): RedirectResponse
    {
        return Redirect::route('admin.websites.show', ['website' => $website, 'tab' => 'wordpress']);
    }
}
