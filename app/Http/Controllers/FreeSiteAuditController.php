<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailWebsiteAuditReportRequest;
use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Jobs\GenerateWebsiteAudit;
use App\Mail\WebsiteAuditLeadReceived;
use App\Mail\WebsiteAuditReport;
use App\Models\MarketingConversion;
use App\Models\WebsiteAudit;
use App\Services\MarketingAuditResearch;
use App\Services\MarketingAuditScreenshot;
use App\Services\MarketingTurnstileVerifier;
use App\Support\MarketingJourney;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        $request->session()->put('marketing.website_audit_id', $audit->public_id);

        return redirect()->route('marketing.website-audits.show', $audit);
    }

    public function show(Request $request, WebsiteAudit $websiteAudit, MarketingAuditResearch $research, MarketingAuditScreenshot $screenshot): View
    {
        abort_if($websiteAudit->hasExpired(), 404);

        $request->session()->put('marketing.website_audit_id', $websiteAudit->public_id);

        $events = MarketingConversion::query()
            ->whereIn('deduplication_key', array_map(
                fn (string $name): string => hash('sha256', $name.':'.$websiteAudit->public_id),
                $websiteAudit->isReadyToDisplay() ? ['audit_submitted', 'audit_completed'] : ['audit_submitted'],
            ))
            ->get()->map(fn (MarketingConversion $conversion): array => $conversion->payload())->all();

        $seo = data_get($websiteAudit->insights, 'seo');

        return view('marketing.website-audit', [
            'audit' => $websiteAudit,
            'marketingEvents' => $events,
            'projection' => is_array($seo) ? $research->projection($seo) : null,
            'rankings' => is_array($seo) ? $research->rankingHighlights($seo) : ['page_one' => [], 'striking_distance' => [], 'other' => []],
            'screenshotUrl' => $websiteAudit->isReadyToDisplay() && Storage::disk('local')->exists($screenshot->pathFor($websiteAudit))
                ? route('marketing.website-audits.preview', $websiteAudit)
                : null,
        ]);
    }

    public function preview(WebsiteAudit $websiteAudit, MarketingAuditScreenshot $screenshot): StreamedResponse
    {
        abort_if($websiteAudit->hasExpired(), 404);
        abort_unless($websiteAudit->isReadyToDisplay(), 404);

        $path = $screenshot->pathFor($websiteAudit);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600', 'Content-Type' => 'image/jpeg']);
    }

    public function emailReport(EmailWebsiteAuditReportRequest $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        $email = $request->validated('email');

        $savedAudit = DB::transaction(function () use ($websiteAudit, $email, $request): WebsiteAudit {
            $audit = WebsiteAudit::query()->lockForUpdate()->findOrFail($websiteAudit->id);

            if ($audit->report_requested_at !== null) {
                if ($audit->email !== $email) {
                    throw ValidationException::withMessages(['email' => 'This report has already been requested by email.']);
                }

                return $audit;
            }

            $audit->update([
                'email' => $email,
                'personal_review_requested_at' => $request->boolean('personal_review') ? now() : null,
                'personal_review_due_at' => $request->boolean('personal_review') ? now()->addWeekday() : null,
                'marketing_consent_at' => $request->boolean('marketing_consent') ? now() : null,
                'marketing_consent_version' => $request->boolean('marketing_consent') ? WebsiteAudit::MARKETING_CONSENT_VERSION : null,
                'report_requested_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);

            Mail::to($email)->queue(new WebsiteAuditReport($audit));
            Mail::to(config('marketing.audit_notification_email'))->queue(new WebsiteAuditLeadReceived($audit));

            return $audit;
        });

        return back()->with('report_email_status', $savedAudit->personal_review_requested_at ? 'Your report is on its way. Ross will email your recommendations within one working day.' : 'Your report is on its way.');
    }

    public function emailPreferences(Request $request, WebsiteAudit $websiteAudit): View
    {
        return view('marketing.audit-email-preferences', ['audit' => $websiteAudit]);
    }

    public function unsubscribe(Request $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        abort_unless($websiteAudit->email, 404);
        WebsiteAudit::query()->where('email', $websiteAudit->email)->update([
            'marketing_consent_at' => null,
            'marketing_consent_withdrawn_at' => now(),
        ]);

        return back()->with('status', 'You have unsubscribed from ongoing website advice.');
    }

    public function status(WebsiteAudit $websiteAudit): JsonResponse
    {
        abort_if($websiteAudit->hasExpired(), 404);

        return response()->json([
            'status' => $websiteAudit->status,
            'completed' => $websiteAudit->isReadyToDisplay(),
            'failed' => $websiteAudit->status === WebsiteAudit::STATUS_FAILED,
            'ai_visibility_status' => data_get($websiteAudit->insights, 'ai_visibility.status'),
        ]);
    }
}
