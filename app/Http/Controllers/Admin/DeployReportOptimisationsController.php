<?php

namespace App\Http\Controllers\Admin;

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Services\OptimisationDeploymentManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class DeployReportOptimisationsController extends Controller
{
    public function __invoke(Request $request, Website $website, WebsiteHealthReport $websiteHealthReport, OptimisationDeploymentManager $optimisations): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);

        $drafts = $website->optimisations()
            ->whereHas('page', fn ($query) => $query->where('website_health_report_id', $websiteHealthReport->id))
            ->whereIn('status', [OptimisationStatus::Draft, OptimisationStatus::Approved])
            ->where('deployment_method', DeploymentMethod::Pixel)
            ->oldest('id')
            ->get();

        foreach ($drafts as $optimisation) {
            $optimisations->approve($optimisation);
            $optimisations->deploy($optimisation->refresh(), $request->user());
        }

        $count = $drafts->count();

        return Redirect::route('admin.website-health-reports.show', [$website, $websiteHealthReport])
            ->with('status', "{$count} approved Pixel ".($count === 1 ? 'fix is' : 'fixes are').' now live.');
    }
}
