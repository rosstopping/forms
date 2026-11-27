<?php

namespace App\Http\Middleware;

use App\Models\Website;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class StaffReportingWorkspace
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! config('reporting.staff_only_reports') || ! $request->user()?->isAdmin()) {
            return $next($request);
        }
        // Keep evidence permalinks, Google authorisation and active Codex work usable.
        if ($request->routeIs('admin.reporting.*', 'admin.profile.*', 'admin.search-overview', 'admin.search-console.connect', 'admin.search-console.callback', 'admin.search-console.property', 'admin.search-console.property.store', 'admin.search-console.performance', 'admin.search-console.queries.show',
            'admin.content-queue.*', 'admin.content-requests.manual.*', 'admin.website-health-reports.show', 'admin.website-health-report-pages.show',
            'admin.backlink-audits.show', 'admin.competitor-audits.show', 'admin.onboarding.audits.*', 'admin.impersonation.destroy')) {
            return $next($request);
        }

        $website = $request->route('website');
        if ($request->isMethodSafe() && $website instanceof Website) {
            abort_unless($website->isAccessibleBy($request->user()), 403);
            if ($request->routeIs('admin.weekly-overviews.show')) {
                return redirect()->route('admin.reporting.weekly', ['website' => $website, ...$request->only(['weekly_report', 'page', 'reports_page'])]);
            }
            if ($request->routeIs('admin.websites.show', 'admin.websites.section')) {
                $section = $request->route('section');
                $anchor = match ($section) {
                    'seo' => '#rankings', 'content' => '#content', 'health' => '#indexing', default => '',
                };

                return $section === 'search' ? redirect()->route('admin.search-overview', $website) : redirect(route('admin.reporting.site', $website).$anchor);
            }
        }
        if ($request->routeIs('admin.dashboard', 'admin.overview', 'admin.websites.index')) {
            return redirect()->route('admin.reporting.index');
        }
        abort(410, 'This page has been retired from the staff reporting workspace.');
    }
}
