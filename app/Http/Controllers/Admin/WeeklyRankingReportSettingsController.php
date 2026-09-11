<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class WeeklyRankingReportSettingsController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Website $website): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        $enabled = $request->boolean('weekly_ranking_reports_enabled');
        $website->update(['weekly_ranking_reports_enabled' => $enabled]);

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'seo'])
            ->with('status', $enabled ? 'Weekly ranking emails enabled.' : 'Weekly ranking emails disabled.');
    }
}
