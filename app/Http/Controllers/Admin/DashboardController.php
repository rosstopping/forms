<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Services\DashboardSchedule;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardSchedule $schedule): View
    {
        $user = $request->user();

        $websiteQuery = Website::query()
            ->with(['latestHealthReport', 'domains', 'contentPlan'])
            ->withCount([
                'forms',
                'submissions',
                'contentRequests as pending_content_requests_count' => fn ($query) => $query->whereNull('picked_up_at'),
                'optimisations as live_pixel_changes_count' => fn ($query) => $query->where('status', 'deployed')->where('deployment_method', 'pixel'),
            ]);

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
            'pendingContentCount' => $websites->sum('pending_content_requests_count'),
            'livePixelChangesCount' => $websites->sum('live_pixel_changes_count'),
            'submissionsCount' => $websites->sum('submissions_count'),
            'automationSchedule' => $schedule->forWebsites($websites)->take(12),
        ]);
    }
}
