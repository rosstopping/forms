<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\AcceptWebsiteInvitationRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class WebsiteInvitationController extends Controller
{
    public function edit(User $user): View
    {
        abort_if($user->email_verified_at !== null, 410, 'This invitation has already been accepted.');

        return view('auth.accept-website-invitation', compact('user'));
    }

    public function update(AcceptWebsiteInvitationRequest $request, User $user): RedirectResponse
    {
        $user->forceFill([
            'name' => $request->validated('name'),
            'password' => $request->validated('password'),
            'email_verified_at' => now(),
        ])->save();

        return redirect()->route('login')->with('status', 'Your account is ready. You can now sign in.');
    }
}
