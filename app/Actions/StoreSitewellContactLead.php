<?php

namespace App\Actions;

use App\Models\FormSubmission;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteDomain;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class StoreSitewellContactLead
{
    /** @param array{name: string, email: string, agency?: string|null, website?: string|null, goals: string, client_websites?: int|string, offers_seo?: string, type?: string} $enquiry */
    public function handle(array $enquiry, Request $request, bool $agencyBeta = false): FormSubmission
    {
        return DB::transaction(function () use ($enquiry, $request, $agencyBeta): FormSubmission {
            $website = $this->contactWebsite();
            $form = $website->forms()->firstOrCreate(
                ['slug' => $agencyBeta ? 'sitewell-agency-beta' : 'sitewell-contact'],
                [
                    'name' => $agencyBeta ? 'Sitewell agency beta' : 'Sitewell contact form',
                    'is_active' => true,
                    'auto_discovered' => false,
                    'first_seen_at' => now(),
                ],
            );
            $submission = $form->submissions()->create([
                'website_id' => $website->id,
                'source_url' => route($agencyBeta ? 'marketing.agencies' : 'marketing.contact'),
                'source_domain' => parse_url((string) config('app.url'), PHP_URL_HOST),
                'data' => $enquiry,
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'is_spam' => false,
                'status' => 'new',
            ]);

            $form->update(['last_submission_at' => now()]);

            return $submission;
        });
    }

    private function contactWebsite(): Website
    {
        if ($websiteId = config('marketing.contact_website_id')) {
            return Website::query()->findOrFail($websiteId);
        }

        $domain = strtolower((string) parse_url((string) config('app.url'), PHP_URL_HOST));
        $canonicalDomain = WebsiteDomain::canonicalDomain($domain);
        $website = Website::query()
            ->whereHas('domains', fn ($query) => $query->where('verified_domain', $canonicalDomain))
            ->first();

        if ($website) {
            return $website;
        }

        $website = Website::query()->create([
            'user_id' => User::query()->where('role', User::ROLE_ADMIN)->oldest('id')->value('id'),
            'name' => 'Sitewell',
            'is_active' => true,
            'auto_discovered' => false,
            'email_enabled' => true,
            'email_recipients' => [config('forms.default_recipient')],
            'webhook_enabled' => false,
            'health_reports_enabled' => false,
        ]);
        $website->domains()->create(['domain' => $domain, 'is_primary' => true]);

        return $website;
    }
}
