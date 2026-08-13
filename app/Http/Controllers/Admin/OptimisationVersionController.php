<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreOptimisationVersionRequest;
use App\Models\Optimisation;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class OptimisationVersionController extends Controller
{
    public function store(
        StoreOptimisationVersionRequest $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
        Optimisation $optimisation,
        OptimisationDeploymentManager $optimisations,
    ): RedirectResponse {
        $this->ensureScope($website, $websiteHealthReport, $websiteHealthReportPage, $optimisation);
        $optimisations->createVersion($optimisation, $request->string('new_value')->toString(), author: $request->user());

        return Redirect::route('admin.website-health-report-pages.show', [$website, $websiteHealthReport, $websiteHealthReportPage])
            ->with('status', 'A new optimisation version was created.');
    }

    private function ensureScope(Website $website, WebsiteHealthReport $report, WebsiteHealthReportPage $page, Optimisation $optimisation): void
    {
        abort_unless($report->website_id === $website->id, 404);
        abort_unless($page->website_health_report_id === $report->id, 404);
        abort_unless($optimisation->website_id === $website->id && $optimisation->website_health_report_page_id === $page->id, 404);
    }
}
