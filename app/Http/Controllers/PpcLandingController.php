<?php

namespace App\Http\Controllers;

use App\Support\MarketingJourney;
use App\Support\MembershipPlan;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Support\Uri;
use Illuminate\View\View;

class PpcLandingController extends Controller
{
    public function show(Request $request, MarketingJourney $journey, string $page): View
    {
        $landing = config("ppc.pages.{$page}");
        abort_unless(is_array($landing), 404);
        $attribution = $journey->capture($request, $landing['path']);
        $offer = MembershipPlan::activeGrowthOffer();

        return view('marketing.ppc', [
            'landing' => $landing,
            'pageKey' => $page,
            'price' => $offer['price'] ?? MembershipPlan::find(MembershipPlan::GROWTH)['price'],
            'offer' => $offer,
            'attribution' => $attribution,
            'faqs' => [...$landing['faqs'], ...config('ppc.faqs')],
            'canonical' => isset($landing['canonical_landing'])
                ? route('marketing.landing', $landing['canonical_landing'])
                : route('marketing.ppc.'.$page),
        ]);
    }

    public function book(Request $request, MarketingJourney $journey): RedirectResponse
    {
        $conversion = $journey->record('book_call_clicked', (string) Str::uuid(), $journey->capture($request));
        $url = Uri::of(config('marketing.booking_url'))->withQuery([
            'metadata' => ['sitewell_booking' => $conversion->event_id],
        ]);

        return redirect()->away((string) $url);
    }
}
