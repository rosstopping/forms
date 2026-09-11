<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Lab404\Impersonate\Services\ImpersonateManager;

class UserImpersonationController extends Controller
{
    public function store(Request $request, User $user, ImpersonateManager $impersonate): RedirectResponse
    {
        abort_unless($request->user()?->canImpersonate() && $user->canBeImpersonated(), 403);
        abort_if($impersonate->isImpersonating(), 403);
        abort_unless($impersonate->take($request->user(), $user), 403);

        $request->session()->regenerate();

        return redirect()->route('admin.dashboard')->with('status', 'You are now viewing Sitewell as '.$user->name.'.');
    }

    public function destroy(Request $request, ImpersonateManager $impersonate): RedirectResponse
    {
        abort_unless($impersonate->isImpersonating(), 403);
        abort_unless($impersonate->leave(), 403);

        $request->session()->regenerate();

        return redirect()->route('admin.users.index')->with('status', 'You have returned to your administrator account.');
    }
}
