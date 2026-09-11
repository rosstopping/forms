<?php

namespace App\Http\Controllers;

use App\Models\OnboardingLifecycleMessage;
use App\Services\OnboardingLifecycleManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class OnboardingLifecycleClickController extends Controller
{
    public function __invoke(Request $request, OnboardingLifecycleMessage $onboardingLifecycleMessage, OnboardingLifecycleManager $lifecycle): RedirectResponse
    {
        if (! $onboardingLifecycleMessage->clicked_at) {
            $onboardingLifecycleMessage->update(['clicked_at' => now()]);
        }

        return redirect()->to($lifecycle->destination($onboardingLifecycleMessage));
    }
}
