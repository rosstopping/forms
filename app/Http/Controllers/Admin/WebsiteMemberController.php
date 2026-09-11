<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreWebsiteMemberRequest;
use App\Http\Requests\UpdateWebsiteMemberRequest;
use App\Models\User;
use App\Models\Website;
use App\Notifications\WebsiteInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class WebsiteMemberController extends Controller
{
    public function store(StoreWebsiteMemberRequest $request, Website $website): RedirectResponse
    {
        $data = $request->validated();
        $created = false;

        $member = DB::transaction(function () use ($data, $website, &$created): User {
            $website = Website::query()->lockForUpdate()->findOrFail($website->id);
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

            $this->ensureManagerRemains($website, $member, $data['role']);
            $website->members()->syncWithoutDetaching([$member->id => ['role' => $data['role']]]);
            $this->applyMembershipChoice($website, $member, $data);

            return $member;
        });

        $member->notify(new WebsiteInvitation($website, $created));

        return back()->with('status', 'Invitation sent to '.$member->email.'.');
    }

    public function update(UpdateWebsiteMemberRequest $request, Website $website, User $member): RedirectResponse
    {
        DB::transaction(function () use ($member, $request, $website): void {
            $website = Website::query()->lockForUpdate()->findOrFail($website->id);

            abort_unless($website->members()->whereKey($member->id)->exists() || $website->owner?->is($member), 404);

            if ($request->filled('role')) {
                $role = $request->validated('role');
                $this->ensureManagerRemains($website, $member, $role);
                $website->members()->syncWithoutDetaching([$member->id => ['role' => $role]]);
            }

            $this->applyMembershipChoice($website, $member, $request->validated());
        });

        return back()->with('status', 'Website member updated.');
    }

    public function destroy(Website $website, User $member): RedirectResponse
    {
        Gate::authorize('manageMembers', $website);
        DB::transaction(function () use ($member, $website): void {
            $website = Website::query()->lockForUpdate()->findOrFail($website->id);
            $isLegacyOwner = $website->owner?->is($member) === true;

            abort_unless($website->members()->whereKey($member->id)->exists() || $isLegacyOwner, 404);

            $this->ensureManagerRemains($website, $member, null);
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

    /** @param array<string, mixed> $data */
    private function applyMembershipChoice(Website $website, User $member, array $data): void
    {
        if (blank($data['complimentary_membership_tier'] ?? null)) {
            return;
        }

        if ($data['complimentary_membership_tier'] === 'existing') {
            if (! $member->hasActiveMembership()) {
                throw ValidationException::withMessages([
                    'complimentary_membership_tier' => 'This account has no active membership. Choose a complimentary package instead.',
                ]);
            }
        } else {
            $member->update([
                'admin_membership_tier' => $data['complimentary_membership_tier'],
                'admin_membership_expires_at' => filled($data['complimentary_membership_ends_on'] ?? null)
                    ? Carbon::parse($data['complimentary_membership_ends_on'])->endOfDay()
                    : null,
            ]);
        }

        if ($website->user_id && ! $website->members()->whereKey($website->user_id)->exists()) {
            $website->members()->attach($website->user_id, ['role' => Website::MEMBER_ROLE_MANAGER]);
        }

        $website->update(['user_id' => $member->id]);
    }

    private function ensureManagerRemains(Website $website, User $member, ?string $newRole): void
    {
        $members = $website->members()->get(['users.id']);
        $memberRecord = $members->firstWhere('id', $member->id);
        $currentRole = $memberRecord?->pivot?->role;

        if ($currentRole === null && $website->owner?->is($member)) {
            $currentRole = Website::MEMBER_ROLE_MANAGER;
        }

        if ($currentRole !== Website::MEMBER_ROLE_MANAGER || $newRole === Website::MEMBER_ROLE_MANAGER) {
            return;
        }

        $managerIds = $members
            ->filter(fn (User $websiteMember): bool => $websiteMember->pivot?->role === Website::MEMBER_ROLE_MANAGER)
            ->pluck('id');

        if ($website->owner && ! $members->contains('id', $website->owner->id)) {
            $managerIds->push($website->owner->id);
        }

        if ($managerIds->unique()->count() <= 1) {
            throw ValidationException::withMessages([
                'role' => 'Add another manager before changing or removing the website’s only manager.',
            ]);
        }
    }
}
