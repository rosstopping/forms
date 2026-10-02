<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Website;
use App\Support\MembershipPlan;
use App\Support\WebsiteNavigation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class CurrentWebsiteController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'website_id' => ['required', 'integer'],
            'section' => ['nullable', 'string', Rule::in(WebsiteNavigation::SECTIONS)],
        ]);

        $website = Website::query()
            ->accessibleTo($request->user())
            ->findOrFail($data['website_id']);

        $request->user()->forceFill(['current_website_id' => $website->id])->save();

        $section = $data['section'] ?? WebsiteNavigation::DEFAULT_SECTION;
        if ($section === 'google-ads' && (! $website->isManageableBy($request->user())
            || (! $request->user()->isAdmin() && ! $website->owner?->hasMembershipFeature(MembershipPlan::FEATURE_COMPLETE)))) {
            $section = WebsiteNavigation::DEFAULT_SECTION;
        }

        return redirect(WebsiteNavigation::routeFor($website, $section));
    }
}
