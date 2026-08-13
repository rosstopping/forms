<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOptimisationRequest;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class OptimisationController extends Controller
{
    public function store(
        StoreOptimisationRequest $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
        OptimisationDeploymentManager $optimisations,
    ): RedirectResponse {
        abort_unless($websiteHealthReport->website_id === $website->id, 404);
        abort_unless($websiteHealthReportPage->website_health_report_id === $websiteHealthReport->id, 404);

        $optimisations->create($website, $websiteHealthReportPage, $request->validated(), $request->user());

        return Redirect::route('admin.website-health-report-pages.show', [$website, $websiteHealthReport, $websiteHealthReportPage])
            ->with('status', 'Optimisation created as a draft.');
    }
}
