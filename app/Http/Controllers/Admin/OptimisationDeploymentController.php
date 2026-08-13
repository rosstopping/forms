<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Optimisation;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class OptimisationDeploymentController extends Controller
{
    public function deploy(
        Request $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
        Optimisation $optimisation,
        OptimisationDeploymentManager $optimisations,
    ): RedirectResponse {
        $this->authorizeAndScope($request, $website, $websiteHealthReport, $websiteHealthReportPage, $optimisation);
        $optimisations->approve($optimisation);
        $optimisations->deploy($optimisation->refresh(), $request->user());

        return $this->redirect($website, $websiteHealthReport, $websiteHealthReportPage, 'Optimisation approved and deployed through Pixel.');
    }

    public function rollback(
        Request $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
        Optimisation $optimisation,
        OptimisationDeploymentManager $optimisations,
    ): RedirectResponse {
        $this->authorizeAndScope($request, $website, $websiteHealthReport, $websiteHealthReportPage, $optimisation);
        $optimisations->rollback($optimisation, $request->user());

        return $this->redirect($website, $websiteHealthReport, $websiteHealthReportPage, 'Optimisation rolled back.');
    }

    private function authorizeAndScope(Request $request, Website $website, WebsiteHealthReport $report, WebsiteHealthReportPage $page, Optimisation $optimisation): void
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($report->website_id === $website->id, 404);
        abort_unless($page->website_health_report_id === $report->id, 404);
        abort_unless($optimisation->website_id === $website->id && $optimisation->website_health_report_page_id === $page->id, 404);
    }

    private function redirect(Website $website, WebsiteHealthReport $report, WebsiteHealthReportPage $page, string $status): RedirectResponse
    {
        return Redirect::route('admin.website-health-report-pages.show', [$website, $report, $page])->with('status', $status);
    }
}
