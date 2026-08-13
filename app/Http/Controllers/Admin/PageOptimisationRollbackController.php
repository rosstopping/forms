<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class PageOptimisationRollbackController extends Controller
{
    public function __invoke(
        Request $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
        OptimisationDeploymentManager $optimisations,
    ): RedirectResponse {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);
        abort_unless($websiteHealthReportPage->website_health_report_id === $websiteHealthReport->id, 404);

        $count = $optimisations->rollbackPage($websiteHealthReportPage, $request->user());

        return Redirect::route('admin.website-health-report-pages.show', [$website, $websiteHealthReport, $websiteHealthReportPage])
            ->with('status', $count === 1 ? 'One live optimisation rolled back.' : "{$count} live optimisations rolled back.");
    }
}
