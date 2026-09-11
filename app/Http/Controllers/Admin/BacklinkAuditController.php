<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\BacklinkAudit;
use App\Models\BacklinkDomainGap;
use App\Models\BacklinkOpportunity;
use App\Models\ExternalApiUsage;
use App\Models\Website;
use App\Services\BacklinkAuditService;
use App\Services\BacklinkProspectImporter;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class BacklinkAuditController extends Controller
{
    public function store(Request $request, Website $website, BacklinkAuditService $service): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $data = $request->validate(['competitor_ids' => ['sometimes', 'array', 'max:3'], 'competitor_ids.*' => ['integer']]);
        $audit = $service->request($website, $data['competitor_ids'] ?? []);

        return redirect()->route('admin.backlink-audits.show', [$website, $audit])->with('status', in_array($audit->status, ['completed', 'completed_with_errors']) ? 'Reusing the audit within its seven-day refresh window.' : 'Backlink audit requested. Saved stages will be reused.');
    }

    public function show(Request $request, Website $website, BacklinkAudit $audit): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        abort_unless($audit->website_id === $website->id, 404);
        $linkFilter = in_array($request->query('links'), ['live', 'lost', 'broken'], true) ? $request->query('links') : 'all';
        $links = $audit->links()->when($linkFilter === 'live', fn ($query) => $query->where('state', 'live'))->when($linkFilter === 'lost', fn ($query) => $query->where('state', 'lost'))->when($linkFilter === 'broken', fn ($query) => $query->where('broken', true))->orderByDesc('source_domain_rank')->paginate(30, pageName: 'link_page')->withQueryString();
        $pages = $audit->pages()->orderByRaw("CASE WHEN kind = 'own' THEN 0 ELSE 1 END")->orderByDesc('referring_domains')->paginate(30, pageName: 'page_page')->withQueryString();
        $gaps = $audit->domainGaps()->with('prospect')->orderByDesc('priority_score')->paginate(30, pageName: 'gap_page')->withQueryString();
        $audit->load(['competitors', 'opportunities' => fn ($query) => $query->orderByDesc('priority_score')]);
        $cost = ExternalApiUsage::where('backlink_audit_id', $audit->id)->sum('cost');

        return view('admin.websites.backlink-audit', ['website' => $website, 'audit' => $audit, 'links' => $links, 'pages' => $pages, 'gaps' => $gaps, 'linkFilter' => $linkFilter, 'cost' => $cost, 'canManageWebsite' => $website->isManageableBy($request->user()), 'canAccessOutreach' => Gate::allows('access-outreach')]);
    }

    public function queue(Request $request, Website $website, BacklinkOpportunity $opportunity, ContentOpportunityQueuer $queuer): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($opportunity->website_id === $website->id, 404);
        abort_unless($opportunity->type === 'content', 422);
        abort_unless($website->repository || (config('forms.pixel_ui_enabled') && $website->pixel_enabled), 422, 'Connect content delivery before queuing a brief.');
        $queuer->queueBacklink($opportunity, $request->user());

        return redirect()->route('admin.websites.show', [$website, 'tab' => 'content'])->with('status', 'Backlink opportunity added to content todos.');
    }

    public function import(Request $request, Website $website, BacklinkDomainGap $gap, BacklinkProspectImporter $importer): RedirectResponse
    {
        Gate::authorize('access-outreach');
        abort_unless($gap->audit->website_id === $website->id, 404);
        $prospect = $importer->import($gap, $request->user());

        return redirect()->route('admin.prospects.show', $prospect)->with('status', 'Backlink opportunity saved for review. No research or outreach was started.');
    }
}
