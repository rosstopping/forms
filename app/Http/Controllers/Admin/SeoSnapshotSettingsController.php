<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\BackfillSeoHistory;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SeoSnapshotSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Website $website): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $enabled = $request->boolean('seo_weekly_snapshots_enabled');
        $website->update(['seo_weekly_snapshots_enabled' => $enabled]);
        if ($enabled && ! $website->seo_history_backfilled_at) {
            BackfillSeoHistory::dispatch($website);
        }

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'seo'])->with('status', $enabled ? 'Weekly SEO snapshots enabled and historical backfill queued.' : 'Weekly SEO snapshots disabled.');
    }
}
