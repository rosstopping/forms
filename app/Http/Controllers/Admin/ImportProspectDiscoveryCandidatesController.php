<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProspectDiscoveryCandidatesRequest;
use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\ProspectDiscovery;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;

class ImportProspectDiscoveryCandidatesController extends Controller
{
    public function __invoke(ImportProspectDiscoveryCandidatesRequest $request, ProspectDiscovery $prospectDiscovery): RedirectResponse
    {
        $imported = DB::transaction(function () use ($request, $prospectDiscovery): int {
            $candidates = $prospectDiscovery->candidates()->whereIn('id', $request->validated('candidate_ids'))
                ->where('status', 'new')->lockForUpdate()->get();

            foreach ($candidates as $candidate) {
                $prospect = Prospect::query()->firstOrCreate(
                    ['website_url' => $candidate->website_url],
                    ['user_id' => $request->user()->id, 'business_name' => $candidate->business_name, 'website_url' => $candidate->website_url],
                );
                $candidate->update(['status' => 'imported', 'prospect_id' => $prospect->id]);
                $prospect->recordActivity('discovered', 'Imported from the OpenStreetMap prospect finder.', $request->user());

                if ($prospect->wasRecentlyCreated) {
                    AnalyzeProspect::dispatch($prospect)->afterCommit();
                }
            }

            return $candidates->count();
        });

        return back()->with('status', $imported === 1 ? '1 prospect imported and queued for website research. No email has been sent.' : "{$imported} prospects imported and queued for website research. No emails have been sent.");
    }
}
