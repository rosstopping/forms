<?php

namespace App\Http\Controllers;

use App\Actions\StoreSitewellContactLead;
use App\Http\Requests\StoreOnboardingEnquiryRequest;
use App\Mail\OnboardingEnquiryReceived;
use App\Services\MarketingTurnstileVerifier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

class OnboardingEnquiryController extends Controller
{
    public function __invoke(StoreOnboardingEnquiryRequest $request, StoreSitewellContactLead $storeLead, MarketingTurnstileVerifier $turnstile): RedirectResponse
    {
        if (! $turnstile->passes($request->string('cf-turnstile-response')->toString(), $request->ip(), $request->getHost())) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Please complete the security check and try again.',
            ]);
        }

        $enquiry = $request->safe()->only(['name', 'email', 'agency', 'website', 'goals']);
        $enquiry['agency'] ??= null;
        $enquiry['website'] ??= null;
        $submission = $storeLead->handle($enquiry, $request);
        if ($submission->wasRecentlyCreated) {
            Mail::to(config('forms.default_recipient'))->send(new OnboardingEnquiryReceived($enquiry));
        }

        return back()->with('status', 'Thanks — your request is with us. We’ll be in touch shortly.');
    }
}
