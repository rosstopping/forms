<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserOnboardingCallRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Redirect;

class UserOnboardingCallController extends Controller
{
    public function __invoke(UpdateUserOnboardingCallRequest $request, User $user): RedirectResponse
    {
        $timestamps = match ($request->validated('status')) {
            'not_booked' => [
                'onboarding_call_booked_at' => null,
                'onboarding_call_completed_at' => null,
            ],
            'booked' => [
                'onboarding_call_booked_at' => $user->onboarding_call_booked_at ?? now(),
                'onboarding_call_completed_at' => null,
            ],
            'completed' => [
                'onboarding_call_booked_at' => $user->onboarding_call_booked_at ?? now(),
                'onboarding_call_completed_at' => $user->onboarding_call_completed_at ?? now(),
            ],
        };

        $user->forceFill($timestamps)->save();

        return Redirect::back()->with('status', 'Onboarding call status updated.');
    }
}
