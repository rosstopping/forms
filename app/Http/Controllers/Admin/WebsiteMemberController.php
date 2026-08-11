<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteMemberRequest;
use App\Http\Requests\UpdateWebsiteMemberRequest;
use App\Models\User;
use App\Models\Website;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;

class WebsiteMemberController extends Controller
{
    public function store(StoreWebsiteMemberRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();
        $website->members()->syncWithoutDetaching([$data['user_id'] => ['role' => $data['role']]]);

        return back()->with('status', 'Website member added.');
    }

    public function update(UpdateWebsiteMemberRequest $request, Website $website, User $member): RedirectResponse
    {
        abort_unless($website->members()->whereKey($member->id)->exists(), 404);
        $website->members()->updateExistingPivot($member->id, ['role' => $request->validated('role')]);

        return back()->with('status', 'Website member updated.');
    }

    public function destroy(Website $website, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $website);
        abort_unless($website->members()->whereKey($member->id)->exists(), 404);
        $website->members()->detach($member->id);

        return back()->with('status', 'Website member removed.');
    }
}
