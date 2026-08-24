<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Support\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class UserController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

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
        abort_unless(Auth::user()?->isAdmin(), 403);

        return view('admin.users.create', ['plans' => MembershipPlan::all()]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(Auth::user()?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'admin_membership_tier' => ['nullable', 'string', Rule::in(array_keys(MembershipPlan::all()))],
        ]);

        $data['password'] = Hash::make($data['password']);

        User::query()->create($data);

        return Redirect::route('admin.users.index')->with('status', 'User created.');
    }

    public function edit(Request $request, User $user): View
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return view('admin.users.edit', [
            'plans' => MembershipPlan::all(),
            'user' => $user,
        ]);
    }

    public function update(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique(User::class)->ignore($user)],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'role' => ['required', 'string', Rule::in([User::ROLE_ADMIN, User::ROLE_USER])],
            'admin_membership_tier' => ['nullable', 'string', Rule::in(array_keys(MembershipPlan::all()))],
        ]);

        if (filled($data['password'] ?? null)) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }

        $user->update($data);

        return Redirect::route('admin.users.index')->with('status', 'User updated.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        if ($request->user()->is($user)) {
            throw ValidationException::withMessages(['user' => 'You cannot delete your own account.']);
        }

        if ($user->websites()->exists()) {
            throw ValidationException::withMessages(['user' => 'Reassign or delete this user’s websites before deleting their account.']);
        }

        if ($user->isAdmin() && User::query()->where('role', User::ROLE_ADMIN)->count() === 1) {
            throw ValidationException::withMessages(['user' => 'The final administrator account cannot be deleted.']);
        }

        $user->delete();

        return Redirect::route('admin.users.index')->with('status', 'User deleted.');
    }
}
