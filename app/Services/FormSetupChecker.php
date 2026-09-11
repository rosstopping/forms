<?php

namespace App\Services;

use App\Http\Middleware\AllowFormSubmissionCors;
use App\Models\Form;
use App\Models\WebsiteDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class FormSetupChecker
{
    public function __construct(private FormSettingsResolver $settings, private SourceWebsiteResolver $websites, private FormResolver $forms) {}

    /** @return list<array{key: string, label: string, status: string, message: string, action: string}> */
    public function check(Form $form): array
    {
        $website = $form->website;
        $website->loadMissing(['domains', 'members']);
        $checks = [];
        $add = function (string $key, string $label, bool $passes, string $message, string $action) use (&$checks): void {
            $checks[] = compact('key', 'label', 'message', 'action') + ['status' => $passes ? 'passed' : 'needs_attention'];
        };
        $add('website_active', 'Website enabled', $website->is_active, $website->is_active ? 'The website accepts submissions.' : 'This website is disabled and rejects submissions.', 'settings');
        $add('form_active', 'Form enabled', $form->is_active, $form->is_active ? 'The form accepts submissions.' : 'This form is disabled and rejects submissions.', 'form');
        $resolvedForm = $this->forms->resolve($website, $form->name);
        $add('form_reference', 'Form lookup', $resolvedForm?->is($form) === true, $resolvedForm?->is($form)
            ? 'The configured form name resolves to this form. Use this name in the _form_name field.'
            : 'The form name does not resolve to this record. Check the form name and reference with Sitewell support.', 'support');

        $domains = $website->domains->where('ownership_status', WebsiteDomain::OWNERSHIP_VERIFIED);
        $domainsResolve = $domains->isNotEmpty() && $domains->every(function (WebsiteDomain $domain) use ($website): bool {
            $request = Request::create('/submit', 'POST', server: ['HTTP_ORIGIN' => 'https://'.$domain->domain]);

            return $this->websites->resolve($request)?->is($website) === true;
        });
        $add('origins', 'Website domain mapping', $domainsResolve, $domainsResolve
            ? 'Verified domains resolve to this website. The actual page origin has not been tested.'
            : 'Add and verify a domain that resolves to this website before collecting submissions.', 'search');
        $cors = app(AllowFormSubmissionCors::class)->handle(Request::create('/submit', 'OPTIONS'), fn () => response()->noContent());
        $add('cors', 'Cross-origin configuration', $cors->headers->get('Access-Control-Allow-Origin') === '*' && str_contains($cors->headers->get('Access-Control-Allow-Methods', ''), 'POST'), 'The submission middleware permits cross-origin POST requests. Browser connectivity has not been tested.', 'support');

        $turnstileRequired = $website->turnstile_enabled && ! $website->auto_discovered;
        $turnstileConfigured = filled($website->turnstile_site_key) && filled($website->turnstile_secret_key);
        $add('turnstile', 'Spam protection configuration', ! $turnstileRequired || $turnstileConfigured, ! $turnstileRequired
            ? 'Turnstile is not required for this website. Existing spam checks still apply.'
            : ($turnstileConfigured ? 'Turnstile keys are present. Widget installation and token verification have not been tested.' : 'Turnstile is enabled but a site key or secret key is missing.'), 'settings');

        $emailEnabled = $this->settings->resolveEmailEnabled($form);
        $recipients = $this->settings->resolveEmailRecipients($form);
        $validRecipients = $recipients !== [] && Validator::make(['recipients' => $recipients], ['recipients' => 'array', 'recipients.*' => 'required|email:rfc'])->passes();
        $add('recipients', 'Notification recipients', ! $emailEnabled || $validRecipients, ! $emailEnabled ? 'Email notifications are switched off; submissions remain in Leads.' : ($validRecipients ? 'Eligible notification recipients are configured.' : 'Email notifications are enabled but no valid eligible recipient list is configured. Viewer members do not receive these notifications.'), 'form');
        if ($emailEnabled) {
            $mailReady = Validator::make(['from' => config('mail.from.address')], ['from' => 'required|email:rfc'])->passes()
                && $this->mailerConfigured((string) config('mail.default'));
            $add('mail', 'Email transport configuration', $mailReady, $mailReady ? 'A delivery transport and sender are configured. No email was sent; inbox delivery is untested.' : 'Email delivery configuration needs attention. Ask Sitewell support to check the mail transport, credentials and sender.', 'support');
        }

        $webhookEnabled = $this->settings->resolveWebhookEnabled($form);
        $url = $this->settings->resolveWebhookUrl($form);
        $validWebhook = is_string($url) && filter_var($url, FILTER_VALIDATE_URL) !== false
            && in_array(strtolower((string) parse_url($url, PHP_URL_SCHEME)), ['http', 'https'], true)
            && parse_url($url, PHP_URL_USER) === null && parse_url($url, PHP_URL_PASS) === null;
        $add('webhook', 'Webhook configuration', ! $webhookEnabled || $validWebhook, ! $webhookEnabled ? 'Webhook delivery is switched off.' : ($validWebhook ? 'A webhook URL is configured. Reachability and delivery have not been tested.' : 'Webhook delivery is enabled but its URL is missing or invalid.'), 'form');

        return $checks;
    }

    /** @param list<string> $visited */
    private function mailerConfigured(string $name, array $visited = []): bool
    {
        if (in_array($name, $visited, true)) {
            return false;
        }
        $mailer = config('mail.mailers.'.$name, []);

        return match ($mailer['transport'] ?? null) {
            'smtp' => filled($mailer['host'] ?? null) || filled($mailer['url'] ?? null),
            'sendmail' => filled($mailer['path'] ?? null),
            'postmark' => filled(config('services.postmark.token')),
            'mailgun' => filled(config('services.mailgun.domain')) && filled(config('services.mailgun.secret')),
            'resend' => filled(config('services.resend.key')),
            'ses', 'ses-v2' => filled(config('services.ses.region')),
            'failover', 'roundrobin' => collect($mailer['mailers'] ?? [])->contains(fn (string $child): bool => $this->mailerConfigured($child, [...$visited, $name])),
            default => false,
        };
    }
}
