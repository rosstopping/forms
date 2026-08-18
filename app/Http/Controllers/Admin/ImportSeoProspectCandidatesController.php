<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ImportSeoProspectCandidatesRequest;
use App\Models\SeoProspectSearch;
use App\Services\SeoProspectImporter;
use Illuminate\Http\RedirectResponse;

class ImportSeoProspectCandidatesController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ImportSeoProspectCandidatesRequest $request, SeoProspectSearch $seoProspectSearch, SeoProspectImporter $importer): RedirectResponse
    {
        $count = $importer->import($seoProspectSearch, $request->validated('candidate_ids'), $request->user());

        return back()->with('status', $count.' SEO '.str('candidate')->plural($count).' added to Outreach. Drafts still require approval before sending.');
    }
}
