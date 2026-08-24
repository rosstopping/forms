<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Jobs\GenerateFreeSiteAudit;
use App\Models\Prospect;
use App\Models\User;
use App\Services\ProspectLifecycleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FreeSiteAuditController extends Controller
{
    public function create(): View
    {
        return view('marketing.free-site-audit');
    }

    public function store(StoreFreeSiteAuditRequest $request, ProspectLifecycleManager $lifecycleManager): RedirectResponse
    {
        $owner = User::query()->where('role', User::ROLE_ADMIN)->oldest('id')->firstOrFail();
        $data = $request->safe()->only(['name', 'email', 'business_name', 'website_url']);

        $prospect = DB::transaction(function () use ($data, $owner): Prospect {
            $prospect = Prospect::query()->create([
                'user_id' => $owner->id,
                'business_name' => $data['business_name'],
                'contact_name' => $data['name'],
                'email' => $data['email'],
                'website_url' => $data['website_url'],
                'status' => 'new',
                'analysis_status' => 'pending',
                'notes' => 'Inbound lead from the free site audit.',
            ]);
            $prospect->recordActivity('free_audit_requested', 'Free site audit requested from the marketing website.');

            return $prospect;
        });

        $lifecycleManager->pause($prospect, description: 'Inbound free-audit lead excluded from cold outreach automation.');

        GenerateFreeSiteAudit::dispatch($prospect)->afterCommit();

        return back()->with('status', 'Thanks — your audit is being prepared. We’ll email the results to '.$prospect->email.'.');
    }
}
