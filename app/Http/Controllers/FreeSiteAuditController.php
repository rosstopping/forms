<?php

namespace App\Http\Controllers;

use App\Http\Requests\EmailWebsiteAuditReportRequest;
use App\Http\Requests\StoreFreeSiteAuditRequest;
use App\Http\Requests\UpdateWebsiteAuditGoalRequest;
use App\Jobs\GenerateWebsiteAudit;
use App\Jobs\GenerateWebsiteAuditFullReport;
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
use Illuminate\Support\Facades\URL;
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
        $publicReport = $request->routeIs('marketing.website-audits.show');
        $unlocked = $request->session()->get('marketing.website_audit_review_ids.'.$websiteAudit->public_id) === true;
        $showDetails = $request->routeIs('marketing.website-audits.full', 'admin.onboarding.audits.show') || $unlocked;
        $customerReport = $publicReport || ($request->routeIs('marketing.website-audits.full') && $websiteAudit->report_requested_at !== null);
        if ($request->routeIs('admin.onboarding.audits.show')) {
            abort_unless($request->user()?->isAdmin(), 403);
        }
        abort_if($publicReport && $websiteAudit->hasExpired() && ! $request->user()?->isAdmin(), 404);

        $request->session()->put('marketing.website_audit_id', $websiteAudit->public_id);

        $events = MarketingConversion::query()
            ->whereIn('deduplication_key', array_map(
                fn (string $name): string => hash('sha256', $name.':'.$websiteAudit->public_id),
                $websiteAudit->isReadyToDisplay() ? ['audit_submitted', 'audit_completed'] : ['audit_submitted'],
            ))
            ->get()->map(fn (MarketingConversion $conversion): array => $conversion->payload())->all();

        if ($publicReport && ! $request->user()?->isAdmin()
            && $request->session()->get('marketing_lead_event.audit_id') === $websiteAudit->public_id) {
            $events[] = $request->session()->pull('marketing_lead_event')['payload'];
        }

        $seo = data_get($websiteAudit->insights, 'seo');

        return view('marketing.website-audit', [
            'audit' => $websiteAudit,
            'showDetails' => $showDetails,
            'goalUrl' => $publicReport && $websiteAudit->report_requested_at !== null && $request->session()->get('marketing.website_audit_review_ids.'.$websiteAudit->public_id) === true
                ? URL::temporarySignedRoute('marketing.website-audits.goal', $websiteAudit->expires_at, $websiteAudit)
                : null,
            'engagementUrl' => $customerReport && ! $websiteAudit->hasExpired() && ! $request->user()?->isAdmin() && $websiteAudit->isReadyToDisplay() ? URL::temporarySignedRoute('marketing.website-audits.engagement', $websiteAudit->expires_at, $websiteAudit) : null,
            'marketingEvents' => $publicReport ? $events : [],
            'researchStatusUrl' => $showDetails ? URL::temporarySignedRoute('marketing.website-audits.status', now()->addMinutes(30), $websiteAudit) : null,
            'projection' => array_key_exists('opportunity', $websiteAudit->insights ?? [])
                ? data_get($websiteAudit->insights, 'opportunity.projection')
                : (is_array($seo) ? $research->projection($seo) : null),
            'rankings' => is_array($seo) ? $research->rankingHighlights($seo) : ['page_one' => [], 'striking_distance' => [], 'other' => []],
            'screenshotUrl' => (! $websiteAudit->hasExpired() || $request->user()?->isAdmin()) && $websiteAudit->isReadyToDisplay() && Storage::disk('local')->exists($screenshot->pathFor($websiteAudit))
                ? route('marketing.website-audits.preview', $websiteAudit)
                : null,
        ]);
    }

    public function preview(Request $request, WebsiteAudit $websiteAudit, MarketingAuditScreenshot $screenshot): StreamedResponse
    {
        abort_if($websiteAudit->hasExpired() && ! $request->user()?->isAdmin(), 404);
        abort_unless($websiteAudit->isReadyToDisplay(), 404);

        $path = $screenshot->pathFor($websiteAudit);
        abort_unless(Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, max-age=3600', 'Content-Type' => 'image/jpeg']);
    }

    public function requestFullReport(Request $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);
        abort_unless($websiteAudit->isReadyToDisplay(), 409);

        DB::transaction(function () use ($websiteAudit): void {
            $audit = WebsiteAudit::query()->lockForUpdate()->findOrFail($websiteAudit->id);
            $this->queueFullResearch($audit);
        });

        return redirect()->route('admin.onboarding.audits.show', $websiteAudit);
    }

    private function queueFullResearch(WebsiteAudit $audit): void
    {
        $status = data_get($audit->insights, 'full_report.status');
        $hasLegacyResearch = $status === null && array_key_exists('pages_listed', $audit->insights ?? [])
            && array_key_exists('competitors', $audit->insights ?? [])
            && array_key_exists('ai_visibility', $audit->insights ?? [])
            && data_get($audit->insights, 'ai_visibility.status') !== 'pending';
        if ($hasLegacyResearch || in_array($status, ['queued', 'running', 'completed'], true)) {
            return;
        }
        $audit->update(['insights' => [...($audit->insights ?? []), 'full_report' => [
            'status' => 'queued',
            'requested_at' => now()->toIso8601String(),
        ]]]);
        GenerateWebsiteAuditFullReport::dispatch($audit)->afterCommit();
    }

    public function fullReportStatus(Request $request, WebsiteAudit $websiteAudit): JsonResponse
    {
        abort_unless($request->user()?->isAdmin(), 403);

        return response()->json([
            'full_report_status' => data_get($websiteAudit->insights, 'full_report.status', 'completed'),
            'ai_visibility_status' => data_get($websiteAudit->insights, 'ai_visibility.status'),
        ]);
    }

    public function emailReport(EmailWebsiteAuditReportRequest $request, WebsiteAudit $websiteAudit, MarketingJourney $journey): RedirectResponse
    {
        $email = $request->validated('email');
        $leadConversion = null;
        $firstCapture = false;

        $savedAudit = DB::transaction(function () use ($websiteAudit, $email, $request, $journey, &$leadConversion, &$firstCapture): WebsiteAudit {
            $audit = WebsiteAudit::query()->lockForUpdate()->findOrFail($websiteAudit->id);

            if ($audit->report_requested_at !== null) {
                if ($audit->email !== $email) {
                    throw ValidationException::withMessages(['email' => 'This report has already been requested by email.']);
                }

                return $audit;
            }

            $audit->update([
                'customer_goal' => $request->validated('customer_goal'),
                'email' => $email,
                'personal_review_requested_at' => $request->boolean('personal_review') ? now() : null,
                'personal_review_due_at' => $request->boolean('personal_review') ? now()->addWeekday() : null,
                'marketing_consent_at' => $request->boolean('marketing_consent') ? now() : null,
                'marketing_consent_version' => $request->boolean('marketing_consent') ? WebsiteAudit::MARKETING_CONSENT_VERSION : null,
                'report_requested_at' => now(),
                'expires_at' => now()->addDays(14),
            ]);

            $firstCapture = true;
            $this->queueFullResearch($audit);

            if (! $request->user()?->isAdmin()) {
                $leadConversion = $journey->record('lead_captured', $audit->public_id, $audit->marketing_attribution ?? []);
            }

            if ($request->filled('engagement_visit_id')) {
                $audit->visits()->firstOrCreate(['visit_id' => $request->validated('engagement_visit_id')])
                    ->update(['email_submitted_at' => now()]);
            }

            Mail::to($email)->queue(new WebsiteAuditReport($audit));
            Mail::to(config('marketing.audit_notification_email'))->queue(new WebsiteAuditLeadReceived($audit));

            return $audit;
        });

        if ($firstCapture) {
            $request->session()->put('marketing.website_audit_review_ids.'.$savedAudit->public_id, true);
        }

        $response = redirect()->to(route('marketing.website-audits.show', $savedAudit).($savedAudit->personal_review_requested_at ? '#audit-follow-up' : '#audit-numbers-title'))
            ->with('report_email_status', ! $firstCapture && ! $request->session()->get('marketing.website_audit_review_ids.'.$savedAudit->public_id)
                ? 'This report was already requested. Open the full-report link in your email to view it.'
                : ($savedAudit->personal_review_requested_at ? 'Thanks. Ross will email your video within one working day.' : 'Your full report is unlocked. We’ve emailed you a link to return to it.'));

        if ($leadConversion !== null) {
            $response->with('marketing_lead_event', ['audit_id' => $savedAudit->public_id, 'payload' => $leadConversion->payload()]);
        }

        return $response;
    }

    public function updateGoal(UpdateWebsiteAuditGoalRequest $request, WebsiteAudit $websiteAudit): RedirectResponse
    {
        $websiteAudit->update(['customer_goal' => $request->validated('customer_goal')]);

        return redirect()->to(route('marketing.website-audits.show', $websiteAudit).'#audit-follow-up')
            ->with('audit_goal_status', 'Thanks. Ross will keep that in mind for your review.');
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

    public function status(Request $request, WebsiteAudit $websiteAudit): JsonResponse
    {
        $unlocked = $request->hasValidSignature() || $request->user()?->isAdmin()
            || $request->session()->get('marketing.website_audit_review_ids.'.$websiteAudit->public_id) === true;
        abort_if($websiteAudit->hasExpired() && ! $request->hasValidSignature() && ! $request->user()?->isAdmin(), 404);

        return response()->json([
            'status' => $websiteAudit->status,
            'completed' => $websiteAudit->isReadyToDisplay(),
            'failed' => $websiteAudit->status === WebsiteAudit::STATUS_FAILED,
            'ai_visibility_status' => $unlocked ? data_get($websiteAudit->insights, 'ai_visibility.status') : null,
            'full_report_status' => $unlocked ? data_get($websiteAudit->insights, 'full_report.status', 'completed') : null,
        ]);
    }
}
