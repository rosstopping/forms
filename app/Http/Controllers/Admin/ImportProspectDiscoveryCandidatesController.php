<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportProspectDiscoveryCandidatesRequest;
use App\Jobs\AnalyzeProspect;
use App\Models\Prospect;
use App\Models\ProspectDiscovery;
use App\Models\ProspectDiscoveryCandidate;
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
                $prospect = $candidate->website_url
                    ? Prospect::query()->firstOrCreate(
                        ['website_url' => $candidate->website_url],
                        ['user_id' => $request->user()->id, 'business_name' => $candidate->business_name, 'website_url' => $candidate->website_url],
                    )
                    : Prospect::query()->create($this->websiteOpportunity($request->user()->id, $candidate));
                $candidate->update(['status' => 'imported', 'prospect_id' => $prospect->id]);
                $prospect->recordActivity('discovered', $candidate->website_url ? 'Imported from the OpenStreetMap prospect finder.' : 'Imported as a website opportunity from OpenStreetMap; website research skipped.', $request->user());

                if ($prospect->wasRecentlyCreated && $prospect->website_url) {
                    AnalyzeProspect::dispatch($prospect)->afterCommit();
                }
            }

            return $candidates->count();
        });

        return back()->with('status', $imported === 1 ? '1 prospect imported. Website research was queued where possible. No email has been sent.' : "{$imported} prospects imported. Website research was queued where possible. No emails have been sent.");
    }

    /** @return array<string, mixed> */
    protected function websiteOpportunity(int $userId, ProspectDiscoveryCandidate $candidate): array
    {
        $tags = data_get($candidate->source_data, 'tags', []);
        $email = data_get($tags, 'email') ?: data_get($tags, 'contact:email');
        $sourceUrl = 'https://www.openstreetmap.org/'.$candidate->source_key;

        return [
            'user_id' => $userId,
            'business_name' => $candidate->business_name,
            'email' => filter_var($email, FILTER_VALIDATE_EMAIL) ? $email : null,
            'website_url' => null,
            'analysis_status' => 'skipped',
            'status' => 'drafted',
            'contact_details' => [
                'emails' => filter_var($email, FILTER_VALIDATE_EMAIL) ? [['value' => $email, 'source_url' => $sourceUrl]] : [],
                'phones' => $candidate->phone ? [['value' => $candidate->phone, 'source_url' => $sourceUrl]] : [],
                'addresses' => $candidate->address ? [['value' => $candidate->address, 'source_url' => $sourceUrl]] : [],
                'contact_page_url' => null,
                'contact_form_url' => null,
            ],
            'outreach_subject' => 'Quick one for '.$candidate->business_name,
            'outreach_body' => "Hi there,\n\nI came across {$candidate->business_name} and couldn't see a website linked from the business listing. Thought Sitewell might be handy if you need one.\n\nI've included a quick video below so you can see what it does. If it looks useful, just reply and we can have a chat.\n\nCheers",
        ];
    }
}
