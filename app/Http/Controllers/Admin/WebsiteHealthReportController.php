<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GenerateWebsiteHealthReport;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Services\WebsiteHealthReportPromptGenerator;
use App\Support\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;
use Illuminate\View\View;

class WebsiteHealthReportController extends Controller
{
    public function __construct(protected WebsiteHealthReportPromptGenerator $promptGenerator) {}

    public function store(Request $request, Website $website): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        $existingReport = $website->healthReports()
            ->whereIn('status', [WebsiteHealthReport::STATUS_PENDING, WebsiteHealthReport::STATUS_RUNNING])
            ->latest('id')
            ->first();

        if ($existingReport) {
            return Redirect::route('admin.website-health-reports.show', [$website, $existingReport])
                ->with('status', 'A health report is already running.');
        }

        $report = $website->healthReports()->create(['status' => WebsiteHealthReport::STATUS_PENDING]);
        GenerateWebsiteHealthReport::dispatch($report);

        return Redirect::route('admin.website-health-reports.show', [$website, $report])
            ->with('status', 'The website health report has been queued.');
    }

    public function show(Request $request, Website $website, WebsiteHealthReport $websiteHealthReport): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);

        $websiteHealthReport->load([
            'website.repository',
            'remediationRuns' => fn ($query) => $query->with('repository')->latest(),
            'pages' => fn ($query) => $query->with('optimisations')->orderBy('depth')->orderBy('url'),
        ]);

        return view('admin.website-health-reports.show', [
            'report' => $websiteHealthReport,
            'canManageWebsite' => $website->isManageableBy($request->user()),
            'canUsePixel' => config('forms.pixel_ui_enabled') && ($request->user()?->isAdmin() || $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_GROWTH)),
            'aiPrompt' => $request->user()?->isAdmin() && $websiteHealthReport->status === WebsiteHealthReport::STATUS_COMPLETED
                ? $this->promptGenerator->generate($websiteHealthReport)
                : null,
        ]);
    }
}
