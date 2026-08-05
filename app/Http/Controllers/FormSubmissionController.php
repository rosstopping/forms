<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreFormSubmissionRequest;
use App\Mail\FormSubmissionReceived;
use App\Models\Form;
use App\Models\FormSubmission;
use App\Models\Website;
use App\Services\FormDataSanitiser;
use App\Services\FormResolver;
use App\Services\FormSettingsResolver;
use App\Services\RedirectResolver;
use App\Services\SourceWebsiteResolver;
use App\Services\WebhookSender;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

class FormSubmissionController extends Controller
{
    public function __construct(
        protected SourceWebsiteResolver $sourceWebsiteResolver,
        protected FormResolver $formResolver,
        protected FormSettingsResolver $formSettingsResolver,
        protected FormDataSanitiser $formDataSanitiser,
        protected RedirectResolver $redirectResolver,
        protected WebhookSender $webhookSender,
    ) {}

    public function store(StoreFormSubmissionRequest $request): mixed
    {
        if ($request->filled('_honeypot')) {
            $website = $this->resolveWebsite($request);
            $form = $this->resolveForm($request, $website);
            $submission = $this->createSubmission($request, $website, $form, true);

            return redirect($this->redirectResolver->resolveSuccess($request, $website ?: $this->createFallbackWebsite($request), $form ?: $this->createFallbackForm($request)));
        }

        if ($this->isRateLimited($request)) {
            return response()->json(['message' => 'Too many requests.'], 429);
        }

        $website = $this->resolveWebsite($request);
        if (! $website) {
            return response()->json(['message' => 'Unable to determine the submitting website.'], 422);
        }

        if (! $website->is_active) {
            return response()->json(['message' => 'This website is disabled.'], 403);
        }

        $form = $this->resolveForm($request, $website);
        if (! $form) {
            return response()->json(['message' => 'Unable to determine the submitting form.'], 422);
        }

        if (! $form->is_active) {
            return response()->json(['message' => 'This form is disabled.'], 403);
        }

        $submission = $this->createSubmission($request, $website, $form, false);

        if (! $submission->is_spam) {
            $this->sendNotifications($submission);
        }

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'message' => 'Your form was submitted successfully.',
                'submission_id' => $submission->id,
                'form' => ['name' => $form->name, 'slug' => $form->slug],
            ]);
        }

        return redirect($this->redirectResolver->resolveSuccess($request, $website, $form));
    }

    protected function resolveWebsite(Request $request): ?Website
    {
        $website = $this->sourceWebsiteResolver->resolve($request);

        if ($website) {
            return $website;
        }

        if (! config('forms.auto_register_websites')) {
            return null;
        }

        $domain = $this->domainFromRequest($request);
        if (! $domain) {
            return null;
        }

        $website = Website::query()->create([
            'name' => $domain,
            'auto_discovered' => true,
            'is_active' => true,
            'email_enabled' => true,
            'email_recipients' => [config('forms.default_recipient')],
            'webhook_enabled' => false,
            'first_seen_at' => now(),
        ]);

        $website->domains()->create(['domain' => $domain, 'is_primary' => true]);

        return $website;
    }

    protected function resolveForm(Request $request, ?Website $website): ?Form
    {
        if (! $website) {
            return null;
        }

        $name = $request->input('_form_name', 'Website form');
        $form = $this->formResolver->resolve($website, $name);

        if ($form) {
            return $form;
        }

        if (! config('forms.auto_register_forms')) {
            return null;
        }

        $normalized = $this->formResolver->normalizeName($name);
        $slug = $this->formResolver->slug($normalized);

        return $website->forms()->create([
            'name' => $normalized,
            'slug' => $slug,
            'auto_discovered' => true,
            'is_active' => true,
            'first_seen_at' => now(),
        ]);
    }

    protected function createSubmission(Request $request, ?Website $website, ?Form $form, bool $isSpam): FormSubmission
    {
        $payload = $request->all();
        $sanitised = $this->formDataSanitiser->sanitise($payload);

        $submission = FormSubmission::query()->create([
            'website_id' => $website?->id,
            'form_id' => $form?->id,
            'source_url' => $request->header('referer') ?: $request->header('origin'),
            'source_domain' => $this->domainFromRequest($request),
            'data' => $sanitised,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'is_spam' => $isSpam,
        ]);

        if ($form) {
            $form->update(['last_submission_at' => now()]);
        }

        return $submission;
    }

    protected function sendNotifications(FormSubmission $submission): void
    {
        $form = $submission->form;
        $website = $submission->website;

        if (! $form || ! $website) {
            return;
        }

        $recipients = $this->formSettingsResolver->resolveEmailRecipients($form);
        $enabled = $this->formSettingsResolver->resolveEmailEnabled($form);
        $webhookEnabled = $this->formSettingsResolver->resolveWebhookEnabled($form);

        if ($enabled && ! empty($recipients)) {
            try {
                Mail::to($recipients)->send(new FormSubmissionReceived($submission));
                $submission->update(['email_sent_at' => now()]);
            } catch (\Throwable $e) {
                $submission->update([
                    'email_failed_at' => now(),
                    'email_error' => $e->getMessage(),
                ]);
                logger()->warning('Form email failed', ['error' => $e->getMessage()]);
            }
        }

        if ($webhookEnabled) {
            try {
                $this->webhookSender->send($submission);
            } catch (\Throwable $e) {
                $submission->update([
                    'webhook_failed_at' => now(),
                    'webhook_error' => $e->getMessage(),
                ]);
                logger()->warning('Form webhook failed', ['error' => $e->getMessage()]);
            }
        }
    }

    protected function domainFromRequest(Request $request): ?string
    {
        $source = $request->header('origin') ?: $request->header('referer');
        if (! $source) {
            return null;
        }

        $parts = parse_url($source);
        if (! $parts || ! isset($parts['host'])) {
            return null;
        }

        $host = strtolower($parts['host']);
        $host = preg_replace('/^www\./i', '', $host);
        $host = idn_to_utf8($host, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46) ?: $host;

        return $host;
    }

    protected function isRateLimited(Request $request): bool
    {
        $domain = $this->domainFromRequest($request);
        $key = 'forms:' . ($domain ?: 'unknown') . ':' . $request->ip();

        return RateLimiter::tooManyAttempts($key, config('forms.rate_limit_per_minute'));
    }
}
