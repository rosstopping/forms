<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\SeoIntelligence\SeoRefreshResult;
use App\Services\SeoIntelligence\SeoRefreshService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class SeoIntelligenceController extends Controller
{
    public function __invoke(Request $request, Website $website, SeoRefreshService $refresh): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);

        $result = $refresh->request($website);
        $message = match ($result->reason) {
            SeoRefreshResult::REASON_QUEUED => 'SEO intelligence generation has been queued.',
            SeoRefreshResult::REASON_IN_PROGRESS => 'SEO intelligence is already being generated.',
            default => 'The latest SEO intelligence is still within the seven-day refresh window.',
        };

        return Redirect::route('admin.websites.show', [$website, 'tab' => 'seo'])->with('status', $message);
    }
}
