<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Jobs\GenerateWebsiteAudit;
use App\Models\MarketingConversion;
use App\Models\WebsiteAudit;
use App\Services\MarketingTurnstileVerifier;
use App\Support\MarketingJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class FreeSiteAuditController extends Controller
{
    public function create(Request $request, MarketingTurnstileVerifier $turnstile, MarketingJourney $journey): View
    {
        return view('marketing.free-site-audit', [
            'turnstileEnabled' => $turnstile->enabled(),
            'turnstileSiteKey' => config('services.turnstile.marketing.site_key'),
            'attribution' => $journey->capture($request),
        ]);
    }

    public function store(StoreFreeSiteAuditRequest $request, MarketingTurnstileVerifier $turnstile, MarketingJourney $journey): RedirectResponse
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
            'marketing_attribution' => $journey->capture($request),
        ]);

        $journey->record('audit_submitted', $audit->public_id, $audit->marketing_attribution);
        GenerateWebsiteAudit::dispatch($audit);

        return redirect()->route('marketing.website-audits.show', $audit);
    }

    public function show(WebsiteAudit $websiteAudit): View
    {
        abort_if($websiteAudit->hasExpired(), 404);

        $events = MarketingConversion::query()
            ->whereIn('deduplication_key', array_map(
                fn (string $name): string => hash('sha256', $name.':'.$websiteAudit->public_id),
                $websiteAudit->isReadyToDisplay() ? ['audit_submitted', 'audit_completed'] : ['audit_submitted'],
            ))
            ->get()->map(fn (MarketingConversion $conversion): array => $conversion->payload())->all();

        return view('marketing.website-audit', ['audit' => $websiteAudit, 'marketingEvents' => $events]);
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
