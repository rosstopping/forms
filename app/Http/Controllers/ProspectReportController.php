<?php

namespace App\Http\Controllers;

use App\Models\Prospect;
use Illuminate\View\View;

class ProspectReportController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Prospect $prospect): View
    {
        return view('prospects.report', compact('prospect'));
    }
}
