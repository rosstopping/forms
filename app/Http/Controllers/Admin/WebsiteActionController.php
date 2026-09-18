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
        $actions->queue($website, $request->user(), $data['action_key']);

        return redirect()->route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions'])->with('status', 'Added to the content queue. The reason, affected page and impact tracking are already recorded.');
    }
}
