<?php

namespace App\Http\Controllers;

use App\Actions\StoreSitewellContactLead;
use App\Http\Requests\StoreOnboardingEnquiryRequest;
use App\Mail\OnboardingEnquiryReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;

class OnboardingEnquiryController extends Controller
{
    public function __invoke(StoreOnboardingEnquiryRequest $request, StoreSitewellContactLead $storeLead): RedirectResponse
    {
        $enquiry = $request->safe()->only(['name', 'email', 'agency', 'website', 'goals']);
        $enquiry['agency'] ??= null;
        $enquiry['website'] ??= null;
        $storeLead->handle($enquiry, $request);
        Mail::to(config('forms.default_recipient'))->send(new OnboardingEnquiryReceived($enquiry));

        return back()->with('status', 'Thanks — your request is with us. We’ll be in touch shortly.');
    }
}
