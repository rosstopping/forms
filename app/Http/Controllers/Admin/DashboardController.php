<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        $websiteQuery = Website::query()
            ->with(['latestHealthReport', 'domains'])
            ->withCount(['forms', 'submissions']);

        if (! $user?->isAdmin()) {
            $websiteQuery->accessibleTo($user);
        }

        $websites = $websiteQuery->orderBy('name')->get();
        $websiteIds = $websites->pluck('id');
        $recentReports = WebsiteHealthReport::query()
            ->with('website:id,name')
            ->whereIn('website_id', $websiteIds)
            ->latest()
            ->limit(8)
            ->get();

        return view('admin.dashboard', [
            'websites' => $websites,
            'recentReports' => $recentReports,
            'needsAttentionCount' => $websites->filter(fn (Website $website): bool => in_array($website->latestHealthReport?->overall_status, ['needs_attention', 'critical'], true))->count(),
            'healthyCount' => $websites->where('latestHealthReport.overall_status', 'healthy')->count(),
            'notAuditedCount' => $websites->whereNull('latestHealthReport')->count(),
        ]);
    }
}
