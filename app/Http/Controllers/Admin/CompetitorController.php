<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteCompetitorRequest;
use App\Http\Requests\UpdateWebsiteCompetitorRequest;
use App\Models\CompetitorAudit;
use App\Models\CompetitorOpportunity;
use App\Models\ExternalApiUsage;
use App\Models\Website;
use App\Models\WebsiteCompetitor;
use App\Services\CompetitorAuditService;
use App\Services\ContentOpportunityQueuer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CompetitorController extends Controller
{
    public function store(StoreWebsiteCompetitorRequest $request, Website $website): RedirectResponse
    {
        $website->competitors()->firstOrCreate(['domain' => $request->validated('domain')]);

        return redirect()->route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'competitors'])->with('status', 'Competitor added. Choose Audit to gather rankings and page evidence.');
    }

    public function update(UpdateWebsiteCompetitorRequest $request, Website $website, WebsiteCompetitor $competitor): RedirectResponse
    {
        abort_unless($competitor->website_id === $website->id, 404);
        $competitor->update($request->validated());

        return redirect()->route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'competitors'])->with('status', 'Competitor selection updated.');
    }

    public function audit(Request $request, Website $website, WebsiteCompetitor $competitor, CompetitorAuditService $service): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($competitor->website_id === $website->id, 404);
        $audit = $service->request($competitor);

        return redirect()->route('admin.competitor-audits.show', [$website, $audit])->with('status', $audit->status === 'completed' ? 'Reusing the audit within its seven-day refresh window.' : 'Audit requested. Saved stages will be reused.');
    }

    public function show(Request $request, Website $website, CompetitorAudit $audit): View
    {
        abort_unless($website->isAccessibleBy($request->user()), 403);
        abort_unless($audit->website_id === $website->id, 404);
        $filter = in_array($request->query('filter'), ['missing', 'outranked'], true) ? $request->query('filter') : 'all';
        $keywords = $audit->keywords()->when($filter === 'missing', fn ($query) => $query->where('comparison', 'missing'))
            ->when($filter === 'outranked', fn ($query) => $query->where('comparison', 'shared')->whereColumn('position', '<', 'our_position'))
            ->orderByDesc('search_volume')->paginate(30)->withQueryString();
        $audit->load(['competitor', 'pages', 'opportunities' => fn ($query) => $query->orderByDesc('priority_score')]);
        $pageKeywords = $audit->keywords()->get(['ranking_url', 'keyword'])->groupBy('ranking_url');
        $cost = ExternalApiUsage::where('competitor_audit_id', $audit->id)->sum('cost');
        $canManageWebsite = $website->isManageableBy($request->user());

        return view('admin.websites.competitor-audit', compact('website', 'audit', 'keywords', 'filter', 'pageKeywords', 'cost', 'canManageWebsite'));
    }

    public function queue(Request $request, Website $website, CompetitorOpportunity $opportunity, ContentOpportunityQueuer $queuer): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        abort_unless($opportunity->website_id === $website->id, 404);
        abort_if($opportunity->audit->competitor->excluded, 422, 'Restore this competitor before queuing a brief.');
        abort_unless($website->repository || (config('forms.pixel_ui_enabled') && $website->pixel_enabled), 422, 'Connect content delivery before queuing a brief.');
        $queuer->queueCompetitor($opportunity, $request->user());

        return redirect()->route('admin.websites.show', [$website, 'tab' => 'content'])->with('status', 'Competitor brief added to content todos.');
    }
}
