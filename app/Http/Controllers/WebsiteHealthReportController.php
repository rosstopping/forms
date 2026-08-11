<?php

namespace App\Http\Controllers;

use App\Models\WebsiteHealthReport;
use Illuminate\View\View;

class WebsiteHealthReportController extends Controller
{
    public function __invoke(WebsiteHealthReport $websiteHealthReport): View
    {
        $websiteHealthReport->load([
            'website',
            'pages' => fn ($query) => $query->orderBy('depth')->orderBy('url'),
        ]);

        return view('website-health-reports.show', ['report' => $websiteHealthReport]);
    }
}
