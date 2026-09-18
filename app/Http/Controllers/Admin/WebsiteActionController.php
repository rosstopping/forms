<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Services\WebsiteActionCenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebsiteActionController extends Controller
{
    public function store(Request $request, Website $website, WebsiteActionCenter $actions): RedirectResponse
    {
        abort_unless($website->isManageableBy($request->user()), 403);
        $data = $request->validate(['action_key' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        if ($request->input('return_to') === 'overview') {
            abort_unless($request->user()->isAdmin(), 403);
            $request->validate(['overview_site_id' => ['nullable', 'integer', 'min:1']]);
        }
        $actions->queue($website, $request->user(), $data['action_key']);
        if ($request->input('return_to') === 'overview') {
            return redirect()->route('admin.overview', array_filter(['hub' => 'priorities', 'site_id' => $request->integer('overview_site_id')]))
                ->with('status', 'Added to '.$website->name.'’s content queue. Its evidence and impact tracking are recorded.');
        }

        return redirect()->route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions'])->with('status', 'Added to the content queue. The reason, affected page and impact tracking are already recorded.');
    }
}
