<?php

namespace App\Http\Controllers;

use App\Models\WebsiteAudit;
use App\Support\MarketingJourney;
use Illuminate\Http\JsonResponse;
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

        return view('marketing.ppc', [
            'landing' => $landing,
            'pageKey' => $page,
            'attribution' => $attribution,
            'faqs' => [...$landing['faqs'], ...config('ppc.faqs')],
            'canonical' => isset($landing['canonical_landing'])
                ? route('marketing.landing', $landing['canonical_landing'])
                : route('marketing.ppc.'.$page),
        ]);
    }

    public function book(Request $request, MarketingJourney $journey): JsonResponse|RedirectResponse
    {
        $attribution = $journey->capture($request);
        $auditId = $request->session()->get('marketing.website_audit_id');
        if (! $request->user()?->isAdmin() && is_string($auditId) && WebsiteAudit::query()->where('public_id', $auditId)->exists()) {
            $attribution['audit_public_id'] = $auditId;
        }
        $conversion = $journey->record('book_call_clicked', (string) Str::uuid(), $attribution);
        $url = Uri::of(config('marketing.booking_url'))->withQuery([
            'metadata' => ['sitewell_booking' => $conversion->event_id],
        ]);

        if ($request->expectsJson()) {
            return response()->json(['booking_url' => (string) $url]);
        }

        return redirect()->away((string) $url);
    }
}
