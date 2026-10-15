<?php

namespace App\Http\Controllers;

use App\Models\WebsiteGrowthPlan;
use App\Services\WebsiteGrowthPlanPresentation;
use Illuminate\Http\Response;

class WebsiteGrowthPlanReportController extends Controller
{
    public function __invoke(WebsiteGrowthPlan $growthPlan, WebsiteGrowthPlanPresentation $presentation): Response
    {
        abort_unless($growthPlan->isReady(), 404);

        return response()->view('website-growth-plans.report', ['plan' => $growthPlan, 'document' => $presentation->for($growthPlan)])
            ->header('Cache-Control', 'private, no-store')
            ->header('X-Robots-Tag', 'noindex, nofollow')
            ->header('Referrer-Policy', 'no-referrer');
    }
}
