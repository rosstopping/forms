<?php

namespace App\Http\Controllers;

use App\Actions\StoreSitewellContactLead;
use App\Http\Requests\StoreAgencyBetaRequest;
use App\Mail\OnboardingEnquiryReceived;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;

class AgencyMarketingController extends Controller
{
    public function index(): View
    {
        return view('marketing.agencies.index', ['pages' => config('agencies.pages')]);
    }

    public function show(string $slug): View
    {
        $page = config('agencies.pages')[$slug] ?? null;
        abort_unless(is_array($page), 404);

        return view('marketing.agencies.show', compact('page', 'slug'));
    }

    public function store(StoreAgencyBetaRequest $request, StoreSitewellContactLead $storeLead): RedirectResponse
    {
        $enquiry = $request->safe()->only(['name', 'agency', 'email', 'website', 'client_websites', 'offers_seo', 'goals']);
        $enquiry['goals'] ??= 'No additional notes supplied.';
        $enquiry['type'] = 'agency_beta';
        $storeLead->handle($enquiry, $request, agencyBeta: true);
        Mail::to(config('forms.default_recipient'))->queue(new OnboardingEnquiryReceived($enquiry));

        return redirect()->to(route('marketing.agencies').'#join-beta')->with('agency_status', 'Thanks — we’ve received your agency beta enquiry. We’ll be in touch to discuss your websites and whether the beta is a good fit.');
    }
}
