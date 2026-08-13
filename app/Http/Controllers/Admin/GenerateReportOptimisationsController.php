<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\GeneratePagePixelOptimisations;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class GenerateReportOptimisationsController extends Controller
{
    private const AUTOMATED_CHECKS = ['page_title', 'meta_description'];

    public function __invoke(Request $request, Website $website, WebsiteHealthReport $websiteHealthReport): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($websiteHealthReport->website_id === $website->id, 404);

        $pages = $websiteHealthReport->pages()->get()->filter(fn ($page): bool => collect($page->checks)
            ->whereIn('status', ['warning', 'failed'])
            ->whereIn('key', self::AUTOMATED_CHECKS)
            ->isNotEmpty());

        foreach ($pages as $page) {
            GeneratePagePixelOptimisations::dispatch($page, $request->user());
        }

        $count = $pages->count();

        return Redirect::route('admin.website-health-reports.show', [$website, $websiteHealthReport])
            ->with('status', $count === 0 ? 'This report has no title or meta-description issues Sitewell can safely automate yet.' : "Sitewell queued AI fixes for {$count} ".($count === 1 ? 'page.' : 'pages.'));
    }
}
