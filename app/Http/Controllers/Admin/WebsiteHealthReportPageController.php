<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Enums\OptimisationType;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class WebsiteHealthReportPageController extends Controller
{
    public function show(
        Request $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
    ): View {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);
        abort_unless($websiteHealthReportPage->website_health_report_id === $websiteHealthReport->id, 404);

        $websiteHealthReportPage->load([
            'optimisations' => fn ($query) => $query
                ->with(['versions.author', 'deployments.version', 'deployments.performer'])
                ->latest(),
        ]);

        return view('admin.website-health-report-pages.show', [
            'website' => $website,
            'report' => $websiteHealthReport,
            'page' => $websiteHealthReportPage,
            'optimisationTypes' => OptimisationType::cases(),
            'canManageWebsite' => $website->isManageableBy($request->user()),
        ]);
    }
}
