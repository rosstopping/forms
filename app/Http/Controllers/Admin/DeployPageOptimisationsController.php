<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class DeployPageOptimisationsController extends Controller
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

        $drafts = $websiteHealthReportPage->optimisations()
            ->whereIn('status', [OptimisationStatus::Draft, OptimisationStatus::Approved])
            ->where('deployment_method', DeploymentMethod::Pixel)
            ->oldest('id')
            ->get();

        foreach ($drafts as $optimisation) {
            $optimisations->approve($optimisation);
            $optimisations->deploy($optimisation->refresh(), $request->user());
        }

        $count = $drafts->count();

        return Redirect::route('admin.website-health-report-pages.show', [$website, $websiteHealthReport, $websiteHealthReportPage])
            ->with('status', "{$count} approved Pixel fix".($count === 1 ? ' is' : 'es are').' now live.');
    }
}
