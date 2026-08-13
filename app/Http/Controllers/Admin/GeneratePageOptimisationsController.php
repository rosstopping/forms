<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\PixelOptimisationGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Redirect;
use Throwable;

class GeneratePageOptimisationsController extends Controller
{
    public function __invoke(
        Request $request,
        Website $website,
        WebsiteHealthReport $websiteHealthReport,
        WebsiteHealthReportPage $websiteHealthReportPage,
        PixelOptimisationGenerator $generator,
    ): RedirectResponse {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);
        abort_unless($websiteHealthReportPage->website_health_report_id === $websiteHealthReport->id, 404);

        try {
            $count = $generator->generate($website, $websiteHealthReportPage, $request->user());
        } catch (Throwable $exception) {
            Log::error('AI Pixel optimisation generation failed.', [
                'website_id' => $website->id,
                'page_id' => $websiteHealthReportPage->id,
                'exception' => $exception,
            ]);

            return Redirect::route('admin.website-health-report-pages.show', [$website, $websiteHealthReport, $websiteHealthReportPage])
                ->with('error', 'Sitewell could not generate fixes right now. No changes were created or deployed.');
        }

        return Redirect::route('admin.website-health-report-pages.show', [$website, $websiteHealthReport, $websiteHealthReportPage])
            ->with('status', $count === 0 ? 'Sitewell found no safe new Pixel fixes for this page.' : "Sitewell generated {$count} fix".($count === 1 ? '' : 'es').' for approval.');
    }
}
