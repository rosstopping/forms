<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Website;
use App\Support\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->hasAllWebsiteAccess(), 403);

        $users = User::query()
            ->latest('created_at')
            ->paginate(15);

        return view('admin.users.index', [
            'plans' => MembershipPlan::all(),
            'users' => $users,
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless(Auth::user()?->hasAllWebsiteAccess(), 403);

        return view('admin.users.create', ['plans' => MembershipPlan::all(), 'websites' => Website::orderBy('name')->get(['id', 'name'])]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->hasAllWebsiteAccess(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'admin_site_access' => ['sometimes', 'required', Rule::in(['all', 'assigned'])],
            'assigned_website_ids' => ['sometimes', 'array'],
            'assigned_website_ids.*' => ['integer', 'distinct', Rule::exists(Website::class, 'id')],
            'admin_membership_tier' => ['nullable', 'string', Rule::in(array_keys(MembershipPlan::all()))],
            'admin_membership_expires_at' => ['nullable', 'date_format:Y-m-d'],
        ]);

        $data = $this->normalizeMembershipExpiry($data);
        $data['password'] = Hash::make($data['password']);

        $assignments = $data['assigned_website_ids'] ?? [];
        unset($data['assigned_website_ids']);
        DB::transaction(function () use ($data, $assignments): void {
            $user = User::query()->create($data);
            $user->assignedWebsites()->sync($user->isAdmin() ? $assignments : []);
        });

        return Redirect::route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()?->hasAllWebsiteAccess(), 403);

        return view('admin.users.edit', [
            'plans' => MembershipPlan::all(),
            'user' => $user,
            'websites' => Website::orderBy('name')->get(['id', 'name']),
            'assignedWebsiteIds' => $user->assignedWebsites()->pluck('websites.id')->all(),
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->hasAllWebsiteAccess(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'admin_site_access' => ['sometimes', 'required', Rule::in(['all', 'assigned'])],
            'assigned_website_ids' => ['sometimes', 'array'],
            'assigned_website_ids.*' => ['integer', 'distinct', Rule::exists(Website::class, 'id')],
            'admin_membership_tier' => ['nullable', 'string', Rule::in(array_keys(MembershipPlan::all()))],
            'admin_membership_expires_at' => ['nullable', 'date_format:Y-m-d'],
        ]);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        DB::transaction(function () use ($user, $data): void {
            User::query()->where('role', User::ROLE_ADMIN)->where('admin_site_access', 'all')->lockForUpdate()->get(['id']);
            if ($user->hasAllWebsiteAccess() && ($data['role'] !== User::ROLE_ADMIN || ($data['admin_site_access'] ?? $user->admin_site_access) !== 'all')
                && ! User::where('role', User::ROLE_ADMIN)->where('admin_site_access', 'all')->whereKeyNot($user->id)->exists()) {
                throw ValidationException::withMessages(['admin_site_access' => 'Keep at least one admin with access to all websites.']);
            }
            $assignments = $data['assigned_website_ids'] ?? [];
            $syncAssignments = array_key_exists('admin_site_access', $data) || array_key_exists('assigned_website_ids', $data) || $data['role'] !== User::ROLE_ADMIN;
            unset($data['assigned_website_ids']);
            $user->update($this->normalizeMembershipExpiry($data));
            if ($syncAssignments) {
                $user->assignedWebsites()->sync($user->isAdmin() ? $assignments : []);
            }
            if ($user->current_website_id && ! Website::accessibleTo($user)->whereKey($user->current_website_id)->exists()) {
                $user->update(['current_website_id' => null]);
            }
        });

        return Redirect::route('admin.users.index')->with('status', 'User updated.');
    }

    /** @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function normalizeMembershipExpiry(array $data): array
    {
        if (array_key_exists('admin_membership_expires_at', $data)) {
            $data['admin_membership_expires_at'] = filled($data['admin_membership_expires_at'])
                ? Carbon::parse($data['admin_membership_expires_at'])->endOfDay()
                : null;
        }

        if (array_key_exists('admin_membership_tier', $data) && blank($data['admin_membership_tier'])) {
            $data['admin_membership_expires_at'] = null;
        }

        return $data;
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->hasAllWebsiteAccess(), 403);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        DB::transaction(function () use ($user): void {
            $admins = User::where('role', User::ROLE_ADMIN)->where('admin_site_access', 'all')->lockForUpdate()->get(['id']);
            if ($user->hasAllWebsiteAccess() && $admins->count() <= 1) {
                throw ValidationException::withMessages(['user' => 'The final administrator with all-website access cannot be deleted.']);
            }
            $user->delete();
        });

        return Redirect::route('admin.users.index')->with('status', 'User deleted.');
    }
}
