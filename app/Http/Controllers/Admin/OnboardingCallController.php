<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class OnboardingCallController extends Controller
{
    public function __invoke(Request $request): RedirectResponse
    {
        $user = $request->user();

        abort_unless(
            $user?->onboarding_status === 'trial_active'
                && $user->onboarding_trial_ends_at?->isFuture()
                && ! $user->onboarding_call_completed_at,
            404,
        );

        if (! $user->onboarding_call_booking_started_at) {
            $user->forceFill(['onboarding_call_booking_started_at' => now()])->save();
        }

        return Redirect::away((string) config('marketing.booking_url'));
    }
}
