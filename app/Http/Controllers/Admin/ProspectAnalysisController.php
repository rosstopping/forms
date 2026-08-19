<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class ProspectAnalysisController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(Request $request, Prospect $prospect): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        abort_unless($prospect->website_url, 422, 'Add a website before running research.');
        abort_if(in_array($prospect->analysis_status, ['pending', 'running']), 422, 'Research is already running.');
        $prospect->update(['analysis_status' => 'pending', 'analysis_error' => null, 'scheduled_send_at' => null]);
        $prospect->recordActivity('analysis_queued', 'Website research queued again.', $request->user());
        AnalyzeProspect::dispatch($prospect);

        return back()->with('status', 'Website research queued.');
    }
}
