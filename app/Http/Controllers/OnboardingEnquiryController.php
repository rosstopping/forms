<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOnboardingEnquiryRequest;
use App\Mail\OnboardingEnquiryReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class OnboardingEnquiryController extends Controller
{
    public function __invoke(StoreOnboardingEnquiryRequest $request): RedirectResponse
    {
        $enquiry = $request->safe()->only(['name', 'email', 'agency', 'website_count', 'goals']);
        $enquiry['agency'] ??= null;
        Mail::to(config('forms.default_recipient'))->send(new OnboardingEnquiryReceived($enquiry));

        return back()->with('status', 'Thanks — your onboarding request is with us. We’ll be in touch shortly.');
    }
}
