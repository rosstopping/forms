<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteMemberRequest;
use App\Http\Requests\UpdateWebsiteMemberRequest;
use App\Models\User;
use App\Models\Website;
use App\Notifications\WebsiteInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class WebsiteMemberController extends Controller
{
    public function store(StoreWebsiteMemberRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();
        $created = false;

        $member = DB::transaction(function () use ($data, $website, &$created): User {
            $member = User::query()->where('email', $data['email'])->first();

            if (! $member) {
                $member = User::query()->create([
                    'name' => Str::headline(Str::before($data['email'], '@')),
                    'email' => $data['email'],
                    'password' => Str::random(64),
                    'role' => User::ROLE_USER,
                ]);
                $created = true;
            }

            $website->members()->syncWithoutDetaching([$member->id => ['role' => $data['role']]]);

            return $member;
        });

        $member->notify(new WebsiteInvitation($website, $created));

        return back()->with('status', 'Invitation sent to '.$member->email.'.');
    }

    public function update(UpdateWebsiteMemberRequest $request, Website $website, User $member): RedirectResponse
    {
        abort_unless($website->members()->whereKey($member->id)->exists() || $website->owner?->is($member), 404);
        $website->members()->syncWithoutDetaching([$member->id => ['role' => $request->validated('role')]]);

        return back()->with('status', 'Website member updated.');
    }

    public function destroy(Website $website, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $website);
        $isLegacyOwner = $website->owner?->is($member) === true;

        abort_unless($website->members()->whereKey($member->id)->exists() || $isLegacyOwner, 404);

        DB::transaction(function () use ($isLegacyOwner, $member, $website): void {
            $website->members()->detach($member->id);

            if ($isLegacyOwner) {
                $replacementManagerId = $website->members()
                    ->wherePivot('role', Website::MEMBER_ROLE_MANAGER)
                    ->value('users.id');

                $website->update(['user_id' => $replacementManagerId]);
            }
        });

        return back()->with('status', 'Website member removed.');
    }
}
