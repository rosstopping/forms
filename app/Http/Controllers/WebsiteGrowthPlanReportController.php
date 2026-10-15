<?php

namespace App\Http\Controllers;

use App\Models\WebsiteGrowthPlan;
use Illuminate\Http\Response;

class WebsiteGrowthPlanReportController extends Controller
{
    public function __invoke(WebsiteGrowthPlan $growthPlan): Response
    {
        abort_unless($growthPlan->isReady(), 404);

        return response()->view('website-growth-plans.report', ['plan' => $growthPlan, 'preview' => false])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
