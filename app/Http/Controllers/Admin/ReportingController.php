<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\CompetitorOpportunity;
use App\Models\ContentOpportunity;
use App\Models\Website;
use App\Models\WeeklyReport;
use App\Services\ReportingOverview;
use App\Services\ReportingPeriod;
use App\Services\SeoTargetKeywordPerformance;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ReportingController extends Controller
{
    public function index(Request $request, ReportingOverview $overview): View
    {
        $sites = Website::query()->accessibleTo($request->user())->with(['searchConsoleConnection', 'latestWeeklyReport:id,website_id,period_end'])
            ->orderByDesc('is_active')->orderBy('name')->get();
        $rows = $overview->rows($sites);

        return view('admin.reporting.portfolio', ['rows' => $rows, 'attention' => $overview->attention($rows), 'cutoff' => ReportingPeriod::cutoff()]);
    }

    public function site(Request $request, Website $website, ReportingOverview $overview, SeoTargetKeywordPerformance $rankings): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        $website->load(['searchConsoleConnection', 'latestWeeklyReport:id,website_id,period_end', 'latestHealthReport:id,website_id']);
        $rows = $overview->rows(collect([$website]));
        $ideas = collect();
        foreach ([ContentOpportunity::class, CompetitorOpportunity::class] as $source) {
            $ideas = $ideas->concat($source::where('website_id', $website->id)->where('status', 'open')->whereNull('content_request_id')
                ->orderByDesc('priority_score')->latest('id')->limit(10)->get(['title', 'priority_score']));
        }

        return view('admin.reporting.site', ['row' => $rows->first(), 'website' => $website, 'rankings' => $rankings->latest($website),
            'attention' => $overview->attention($rows), 'ideas' => $ideas->sortByDesc('priority_score')->take(10), 'cutoff' => ReportingPeriod::cutoff()]);
    }

    public function weekly(Request $request, Website $website): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        $reports = WeeklyReport::where('website_id', $website->id)->whereNotNull('generated_at')->latest('period_end');
        $report = $request->filled('weekly_report') ? (clone $reports)->whereKey($request->integer('weekly_report'))->firstOrFail() : (clone $reports)->first();

        return view('admin.reporting.weekly', ['website' => $website, 'report' => $report,
            'history' => $reports->paginate(12, ['id', 'period_start', 'period_end'])->withQueryString()]);
    }
}
