<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Jobs\GenerateWebsiteAudit;
use App\Models\WebsiteAudit;
use App\Services\MarketingTurnstileVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FreeSiteAuditController extends Controller
{
    public function create(MarketingTurnstileVerifier $turnstile): View
    {
        return view('marketing.free-site-audit', [
            'turnstileEnabled' => $turnstile->enabled(),
            'turnstileSiteKey' => config('services.turnstile.marketing.site_key'),
        ]);
    }

    public function store(StoreFreeSiteAuditRequest $request, MarketingTurnstileVerifier $turnstile): RedirectResponse
    {
        if (! $turnstile->passes(
            $request->string('cf-turnstile-response')->toString(),
            $request->ip(),
            $request->getHost(),
        )) {
            throw ValidationException::withMessages([
                'cf-turnstile-response' => 'Please confirm you are human and try again.',
            ]);
        }

        $websiteUrl = $request->string('website_url')->toString();
        $audit = WebsiteAudit::query()->create([
            'website_url' => $websiteUrl,
            'domain' => (string) parse_url($websiteUrl, PHP_URL_HOST),
            'expires_at' => now()->addDay(),
        ]);

        GenerateWebsiteAudit::dispatch($audit);

        return redirect()->route('marketing.website-audits.show', $audit);
    }

    public function show(WebsiteAudit $websiteAudit): View
    {
        abort_if($websiteAudit->hasExpired(), 404);

        return view('marketing.website-audit', ['audit' => $websiteAudit]);
    }

    public function status(WebsiteAudit $websiteAudit): JsonResponse
    {
        abort_if($websiteAudit->hasExpired(), 404);

        return response()->json([
            'status' => $websiteAudit->status,
            'completed' => $websiteAudit->isReadyToDisplay(),
            'failed' => $websiteAudit->status === WebsiteAudit::STATUS_FAILED,
        ]);
    }
}
