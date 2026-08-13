<?php

namespace Database\Seeders;

use App\Models\BusinessProfileAudit;
use App\Models\BusinessProfilePost;
use App\Models\BusinessProfileRecommendation;
use App\Models\BusinessProfileReview;
use App\Models\ContentGeneration;
use App\Models\Form;
use App\Models\GithubInstallation;
use App\Models\RemediationRun;
use App\Models\SearchOpportunity;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteRepository;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoWebsiteSeeder extends Seeder
{
    public const DOMAIN = 'willowandstone.demo.test';

    public const OWNER_EMAIL = 'demo.owner@sitewell.test';

    public function run(): void
    {
        DB::transaction(function (): void {
            $this->removeExistingDemo();

            $owner = $this->demoUser('Sophie Bennett', self::OWNER_EMAIL);
            $manager = $this->demoUser('Daniel Reed', 'demo.manager@sitewell.test');
            $viewer = $this->demoUser('Amelia Hart', 'demo.viewer@sitewell.test');

            $website = Website::query()->create([
                'user_id' => $owner->id,
                'name' => 'Willow & Stone Garden Rooms',
                'is_active' => true,
                'auto_discovered' => false,
                'email_enabled' => true,
                'email_recipients' => ['hello@willowandstone.co.uk', 'sophie@willowandstone.co.uk'],
                'autoresponder_enabled' => true,
                'autoresponder_subject' => 'Thanks for contacting Willow & Stone',
                'autoresponder_body' => "Hi {name},\n\nThanks for getting in touch with Willow & Stone. We have received your enquiry and one of our garden room specialists will reply within one working day.\n\nWarm regards,\nThe Willow & Stone team",
                'webhook_enabled' => false,
                'health_reports_enabled' => true,
                'turnstile_enabled' => true,
                'first_seen_at' => now()->subMonths(8),
            ]);

            $website->members()->attach([
                $manager->id => ['role' => Website::MEMBER_ROLE_MANAGER],
                $viewer->id => ['role' => Website::MEMBER_ROLE_VIEWER],
            ]);
            $website->domains()->create(['domain' => self::DOMAIN, 'is_primary' => true]);

            [$quoteForm, $brochureForm, $contactForm] = $this->createForms($website);
            $this->createLeads($website, $manager, $quoteForm, $brochureForm, $contactForm);

            $installation = GithubInstallation::query()->updateOrCreate(
                ['installation_id' => 9_900_001],
                [
                    'installed_by' => $owner->id,
                    'account_id' => 9_900_001,
                    'account_login' => 'willow-and-stone-demo',
                    'account_type' => 'Organization',
                    'repository_selection' => 'selected',
                    'permissions' => ['contents' => 'write', 'pull_requests' => 'write', 'metadata' => 'read'],
                    'status' => GithubInstallation::STATUS_ACTIVE,
                ],
            );
            $repository = $website->repository()->create([
                'github_installation_id' => $installation->id,
                'repository_id' => 9_900_001,
                'full_name' => 'willow-and-stone/website',
                'default_branch' => 'main',
                'private' => true,
                'permissions' => ['contents' => 'write', 'pull_requests' => 'write'],
                'project_path' => '/',
            ]);

            [$previousReport, $latestReport] = $this->createHealthReports($website);
            $this->createRemediationRuns($owner, $repository, $previousReport, $latestReport);
            $this->createSearchAndContentData($website, $owner, $repository);
            $this->createBusinessProfileData($website, $owner);
        });
    }

    private function removeExistingDemo(): void
    {
        $website = Website::query()
            ->whereHas('domains', fn ($query) => $query->where('domain', self::DOMAIN))
            ->first();

        if (! $website) {
            return;
        }

        ContentGeneration::query()->whereHas('plan', fn ($query) => $query->whereBelongsTo($website))->delete();
        RemediationRun::query()->whereHas('report', fn ($query) => $query->whereBelongsTo($website))->delete();
        $website->delete();
    }

    private function demoUser(string $name, string $email): User
    {
        return User::query()->updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Str::random(64),
                'role' => User::ROLE_USER,
                'email_verified_at' => now(),
            ],
        );
    }

    /** @return array{Form, Form, Form} */
    private function createForms(Website $website): array
    {
        $quote = $website->forms()->create([
            'name' => 'Request a Quote',
            'slug' => 'request-a-quote',
            'is_active' => true,
            'auto_discovered' => true,
            'email_subject_override' => 'New garden room enquiry #{submission_id}',
            'first_seen_at' => now()->subMonths(8),
            'last_submission_at' => now()->subHours(2),
        ]);
        $brochure = $website->forms()->create([
            'name' => 'Download the Brochure',
            'slug' => 'brochure-download',
            'is_active' => true,
            'auto_discovered' => true,
            'autoresponder_enabled_override' => true,
            'autoresponder_subject_override' => 'Your Willow & Stone brochure',
            'autoresponder_body_override' => "Hi {name},\n\nThanks for requesting our brochure. A member of the team will also be in touch if you would like help choosing the right garden room.",
            'first_seen_at' => now()->subMonths(6),
            'last_submission_at' => now()->subDay(),
        ]);
        $contact = $website->forms()->create([
            'name' => 'General Contact',
            'slug' => 'contact',
            'is_active' => true,
            'auto_discovered' => true,
            'first_seen_at' => now()->subMonths(8),
            'last_submission_at' => now()->subDays(2),
        ]);

        return [$quote, $brochure, $contact];
    }

    private function createLeads(Website $website, User $manager, Form $quote, Form $brochure, Form $contact): void
    {
        $leads = [
            [$quote, 'new', false, null, now()->subHours(2), ['name' => 'Olivia Harper', 'email' => 'olivia.harper@example.com', 'phone' => '07700 900321', 'budget' => '£25,000–£35,000', 'message' => 'We would love a garden office with space for two desks and built-in storage. Could somebody arrange a site visit?']],
            [$quote, 'contacted', false, now()->subDay(), now()->subDays(2), ['name' => 'James Wilson', 'email' => 'james.wilson@example.com', 'phone' => '07700 900654', 'budget' => '£35,000+', 'message' => 'Looking for a premium garden studio that can double as a guest room. Planning to build this autumn.']],
            [$quote, 'qualified', false, now()->addDay(), now()->subDays(4), ['name' => 'Priya Shah', 'email' => 'priya.shah@example.com', 'phone' => '07700 900876', 'budget' => '£20,000–£25,000', 'message' => 'Please quote for a 4m x 3m insulated garden office. Access is through a side gate.']],
            [$quote, 'won', false, null, now()->subDays(12), ['name' => 'Marcus Green', 'email' => 'marcus.green@example.com', 'phone' => '07700 900147', 'budget' => '£30,000', 'message' => 'We are ready to proceed with the cedar garden room discussed during our consultation.']],
            [$contact, 'lost', false, null, now()->subDays(18), ['name' => 'Helen Moore', 'email' => 'helen.moore@example.com', 'message' => 'Do you install outside your current service area?']],
            [$brochure, 'new', false, now()->addDays(3), now()->subDay(), ['name' => 'Ethan Clarke', 'email' => 'ethan.clarke@example.com', 'project_type' => 'Garden gym', 'message' => 'I am comparing options for a garden gym next spring.']],
            [$contact, 'contacted', false, now()->subHours(4), now()->subDays(3), ['name' => 'Grace Evans', 'email' => 'grace.evans@example.com', 'phone' => '07700 900852', 'message' => 'Can you retrofit acoustic panels to an existing garden music room?']],
            [$contact, 'new', true, null, now()->subDays(5), ['name' => 'SEO Services', 'email' => 'sales@example.net', 'message' => 'Guaranteed first page rankings. Buy links today.']],
        ];

        foreach ($leads as $index => [$form, $status, $isSpam, $followUpAt, $createdAt, $data]) {
            $submission = $website->submissions()->create([
                'form_id' => $form->id,
                'source_url' => 'https://'.self::DOMAIN.($form->slug === 'contact' ? '/contact' : '/'.$form->slug),
                'source_domain' => self::DOMAIN,
                'data' => $data,
                'ip_address' => '192.0.2.'.($index + 10),
                'user_agent' => 'Mozilla/5.0 (Demo browser)',
                'is_spam' => $isSpam,
                'status' => $status,
                'notes' => $status === 'qualified' ? 'Site visit booked. Interested in the Signature range with extra glazing.' : null,
                'assigned_to' => in_array($status, ['contacted', 'qualified', 'won'], true) ? $manager->id : null,
                'follow_up_at' => $followUpAt,
                'email_sent_at' => $createdAt->clone()->addMinute(),
                'autoresponder_sent_at' => $isSpam ? null : $createdAt->clone()->addMinutes(2),
            ]);
            $submission->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();
            $submission->recordActivity('created', 'Lead captured from '.$form->name.'.');

            if ($submission->assigned_to) {
                $submission->recordActivity('assigned', 'Lead assigned to '.$manager->name.'.', $manager);
            }
            if ($status !== 'new') {
                $submission->recordActivity('status_changed', 'Status changed to '.ucfirst($status).'.', $manager, ['status' => $status]);
            }
            if ($followUpAt) {
                $submission->recordActivity('follow_up_scheduled', 'Follow-up scheduled for '.$followUpAt->toDayDateTimeString().'.', $manager);
            }
        }
    }

    /** @return array{WebsiteHealthReport, WebsiteHealthReport} */
    private function createHealthReports(Website $website): array
    {
        $previous = $this->createHealthReport($website, now()->subWeek(), 'critical', 19, 6, 4, ['new_issues' => 4, 'resolved_issues' => 0], false);
        $latest = $this->createHealthReport($website, now()->subHours(14), 'needs_attention', 27, 4, 1, ['new_issues' => 1, 'resolved_issues' => 4], true);

        return [$previous, $latest];
    }

    private function createHealthReport(Website $website, Carbon $createdAt, string $overallStatus, int $passed, int $warnings, int $failed, array $changes, bool $includePages): WebsiteHealthReport
    {
        $checks = [
            $this->check('availability', 'http_status', 'Website availability', 'passed', 'The homepage returned HTTP 200.'),
            $this->check('availability', 'response_time', 'Server response time', 'passed', 'The initial response completed in 284 ms.'),
            $this->check('security', 'https', 'HTTPS', 'passed', 'All checked pages use HTTPS.'),
            $this->check('seo', 'robots', 'Search engine access', 'passed', 'Robots.txt allows important pages to be crawled.'),
            $this->check('seo', 'meta_descriptions', 'Page descriptions', 'warning', 'Two service pages have descriptions that could be more specific.'),
            $this->check('performance', 'core_web_vitals', 'Core Web Vitals', 'warning', 'Mobile LCP needs improvement on the gallery page.'),
            $this->check('accessibility', 'image_alt', 'Image alternative text', 'failed', 'Three gallery images are missing alternative text.'),
            $this->check('forms', 'submission_activity', 'Submission activity', 'passed', 'Seven legitimate enquiries and one spam submission were received recently.'),
        ];
        $metrics = [
            'http_status' => 200,
            'response_time_ms' => 284,
            'pages_analyzed' => $includePages ? 5 : 4,
            'forms_count' => 3,
            'submissions' => 8,
            'legitimate_submissions' => 7,
            'spam_submissions' => 1,
            'email_failures' => 0,
            'webhook_failures' => 0,
            'changes' => $changes,
            'pagespeed' => $this->pageSpeedMetrics(),
        ];
        $report = $website->healthReports()->create([
            'status' => WebsiteHealthReport::STATUS_COMPLETED,
            'overall_status' => $overallStatus,
            'passed_checks' => $passed,
            'warning_checks' => $warnings,
            'failed_checks' => $failed,
            'categories' => [
                'availability' => ['passed' => 2, 'warning' => 0, 'failed' => 0],
                'security' => ['passed' => 4, 'warning' => 0, 'failed' => 0],
                'seo' => ['passed' => 9, 'warning' => 2, 'failed' => 0],
                'performance' => ['passed' => 5, 'warning' => 2, 'failed' => 0],
                'accessibility' => ['passed' => 4, 'warning' => 0, 'failed' => 1],
                'forms' => ['passed' => 3, 'warning' => 0, 'failed' => 0],
            ],
            'checks' => $checks,
            'metrics' => $metrics,
            'started_at' => $createdAt->clone()->subMinutes(3),
            'completed_at' => $createdAt,
            'emailed_at' => $createdAt->clone()->addMinutes(2),
        ]);
        $report->forceFill(['created_at' => $createdAt, 'updated_at' => $createdAt])->save();

        if ($includePages) {
            $this->createHealthPages($report);
        }

        return $report;
    }

    private function createHealthPages(WebsiteHealthReport $report): void
    {
        $pages = [
            ['/', 'Bespoke Garden Rooms | Willow & Stone', 'Beautiful, insulated garden rooms designed and installed across Surrey.', 721, 12, 8, 0, [$this->check('seo', 'structured_data_syntax', 'Structured data', 'passed', 'Valid LocalBusiness and Organization schema found.', ['blocks' => 2, 'types' => ['LocalBusiness', 'Organization']])]],
            ['/garden-offices', 'Bespoke Garden Offices in Surrey | Willow & Stone', 'Create a quiet, year-round garden office designed around how you work.', 1034, 15, 11, 0, [$this->check('seo', 'structured_data_recommendation_service', 'Service schema opportunity', 'warning', 'Add Service schema supported by the visible service details.', ['blocks' => 1, 'types' => ['BreadcrumbList']])]],
            ['/garden-studios', 'Luxury Garden Studios', 'Flexible garden studios for creativity, guests and everyday life.', 842, 13, 10, 0, [$this->check('seo', 'structured_data_syntax', 'Structured data', 'passed', 'Valid BreadcrumbList schema found.', ['blocks' => 1, 'types' => ['BreadcrumbList']])]],
            ['/projects', 'Recent Garden Room Projects | Willow & Stone', 'Explore recent garden offices, studios and gyms made for our clients.', 516, 18, 24, 3, [$this->check('accessibility', 'image_alt', 'Image alternative text', 'failed', 'Three project images are missing useful alternative text.'), $this->check('seo', 'structured_data_recommendation_image', 'Image schema opportunity', 'warning', 'Add ImageObject details for featured project photography.', ['blocks' => 0, 'types' => []])]],
            ['/contact', 'Book a Free Garden Room Consultation', 'Tell us about your project and arrange a friendly, no-obligation consultation.', 398, 9, 2, 0, [$this->check('seo', 'structured_data_recommendation_local_business', 'Local business details', 'warning', 'Add opening hours and service area to the LocalBusiness schema.', ['blocks' => 1, 'types' => ['LocalBusiness']])]],
        ];

        foreach ($pages as $depth => [$path, $title, $description, $words, $links, $images, $missingAlt, $specialChecks]) {
            $url = 'https://'.self::DOMAIN.$path;
            $checks = array_merge([
                $this->check('availability', 'http_status', 'HTTP status', 'passed', 'The page returned HTTP 200.'),
                $this->check('seo', 'page_title', 'Page title', 'passed', 'The page has a descriptive title.'),
                $this->check('seo', 'meta_description', 'Meta description', 'passed', 'The page has a useful meta description.'),
            ], $specialChecks);
            $report->pages()->create([
                'url' => $url,
                'url_hash' => hash('sha256', $url),
                'depth' => min($depth, 1),
                'status_code' => 200,
                'response_time_ms' => 218 + ($depth * 31),
                'title' => $title,
                'meta_description' => $description,
                'h1_count' => 1,
                'canonical_url' => $url,
                'is_indexable' => true,
                'word_count' => $words,
                'internal_links_count' => $links,
                'images_count' => $images,
                'missing_alt_count' => $missingAlt,
                'checks' => $checks,
            ]);
        }
    }

    private function createRemediationRuns(User $owner, WebsiteRepository $repository, WebsiteHealthReport $previous, WebsiteHealthReport $latest): void
    {
        $completed = $previous->remediationRuns()->create([
            'website_repository_id' => $repository->id,
            'requested_by' => $owner->id,
            'status' => RemediationRun::STATUS_COMPLETED,
            'findings' => ['site:seo:meta_descriptions', 'site:performance:core_web_vitals'],
            'branch' => 'sitewell/health-fixes-july',
            'commit_sha' => str_repeat('a', 40),
            'pull_request_number' => 18,
            'pull_request_url' => 'https://github.com/willow-and-stone/website/pull/18',
            'pull_request_state' => 'merged',
            'summary' => 'Improved service-page metadata and deferred the gallery carousel JavaScript.',
            'verification' => ['tests' => 'passed', 'build' => 'passed'],
            'started_at' => now()->subDays(6),
            'completed_at' => now()->subDays(6)->addMinutes(14),
            'merged_at' => now()->subDays(5),
        ]);
        $completed->forceFill(['created_at' => now()->subDays(6), 'updated_at' => now()->subDays(5)])->save();

        $latest->remediationRuns()->create([
            'website_repository_id' => $repository->id,
            'requested_by' => $owner->id,
            'status' => RemediationRun::STATUS_PULL_REQUEST_OPEN,
            'copilot_task_id' => (string) Str::uuid(),
            'copilot_task_url' => 'https://github.com/willow-and-stone/website/issues/24',
            'copilot_task_state' => 'completed',
            'findings' => ['page:projects:image_alt', 'page:contact:structured_data_recommendation_local_business'],
            'branch' => 'sitewell/health-fixes-august',
            'commit_sha' => str_repeat('b', 40),
            'pull_request_number' => 24,
            'pull_request_url' => 'https://github.com/willow-and-stone/website/pull/24',
            'pull_request_state' => 'open',
            'summary' => 'Adds descriptive project image text and completes the local business schema.',
            'verification' => ['tests' => 'passed', 'build' => 'passed', 'lighthouse' => 'improved'],
            'started_at' => now()->subHours(12),
            'completed_at' => now()->subHours(11),
        ]);
    }

    private function createSearchAndContentData(Website $website, User $owner, WebsiteRepository $repository): void
    {
        $website->searchConsoleConnection()->create([
            'connected_by' => $owner->id,
            'property_url' => 'sc-domain:'.self::DOMAIN,
            'permission_level' => 'siteOwner',
            'access_token' => 'demo-search-console-token',
            'refresh_token' => 'demo-search-console-refresh-token',
            'access_token_expires_at' => now()->addHour(),
            'opportunities_checked_at' => now()->subHours(10),
        ]);

        $opportunities = [
            ['ranking_gap', 'luxury garden office surrey', '/garden-offices', 'Ranking opportunity for “luxury garden office surrey”', 'This page averaged position 8.4 from 1,840 impressions.', 'Add a clearer Surrey project section, answer common planning questions, and strengthen internal links from recent projects.', 23_184, SearchOpportunity::STATUS_OPEN],
            ['low_ctr', 'bespoke garden rooms', '/', 'Low click-through rate for “bespoke garden rooms”', 'The result appeared 3,260 times with a 1.8% click-through rate at position 4.2.', 'Test a more specific title and description that foregrounds bespoke design, insulation and the free consultation.', 19_560, SearchOpportunity::STATUS_QUEUED],
            ['emerging', 'garden room guest suite', '/garden-studios', 'Emerging query: “garden room guest suite”', 'This query generated 620 new impressions in the latest period.', 'Expand the studio page with an accurate section covering guest use, insulation and practical considerations.', 1_240, SearchOpportunity::STATUS_OPEN],
            ['declining', 'garden gym ideas', '/projects', 'Declining clicks for “garden gym ideas”', 'Clicks fell from 64 to 37 between comparable 28-day periods.', 'Refresh the garden gym project examples and retain the useful layout and equipment guidance.', 3_320, SearchOpportunity::STATUS_OPEN],
        ];
        foreach ($opportunities as [$type, $query, $path, $title, $summary, $recommendation, $score, $status]) {
            $website->searchOpportunities()->create([
                'fingerprint' => hash('sha256', $type.'|'.$query.'|'.$path),
                'type' => $type,
                'status' => $status,
                'query' => $query,
                'page' => 'https://'.self::DOMAIN.$path,
                'title' => $title,
                'summary' => $summary,
                'recommendation' => $recommendation,
                'metrics' => ['current' => ['clicks' => 37, 'impressions' => 1840, 'ctr' => 0.02, 'position' => 8.4]],
                'priority_score' => $score,
                'first_detected_at' => now()->subWeeks(2),
                'last_detected_at' => now()->subHours(10),
            ]);
        }

        $plan = $website->contentPlan()->create([
            'created_by' => $owner->id,
            'enabled' => true,
            'weekday' => 2,
            'hour' => 9,
            'timezone' => 'Europe/London',
            'audience' => 'Homeowners in Surrey and nearby areas who want a beautifully designed, year-round garden office, studio, gym or guest space.',
            'guidance' => 'Friendly, knowledgeable and reassuring. Prefer useful design and planning advice over sales language. Never make planning-permission guarantees.',
            'last_generated_at' => now()->subDays(5),
        ]);
        $generation = $plan->generations()->create([
            'website_repository_id' => $repository->id,
            'requested_by' => $owner->id,
            'scheduled_for' => now()->subDays(5)->toDateString(),
            'status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN,
            'search_performance' => ['clicks' => 428, 'impressions' => 18_640, 'top_queries' => ['bespoke garden rooms', 'garden office surrey', 'garden studio ideas']],
            'prompt' => 'Create a useful guide to planning a garden office layout for two people.',
            'copilot_task_id' => (string) Str::uuid(),
            'copilot_task_url' => 'https://github.com/willow-and-stone/website/issues/22',
            'copilot_task_state' => 'completed',
            'pull_request_number' => 22,
            'pull_request_url' => 'https://github.com/willow-and-stone/website/pull/22',
            'pull_request_state' => 'open',
            'started_at' => now()->subDays(5),
            'completed_at' => now()->subDays(5)->addMinutes(18),
        ]);
        $website->contentRequests()->create([
            'created_by' => $owner->id,
            'content_generation_id' => $generation->id,
            'instructions' => 'Create a practical guide to planning a comfortable garden office for two people, including layout, lighting and storage.',
            'picked_up_at' => now()->subDays(5),
        ]);
        $website->contentRequests()->create([
            'created_by' => $owner->id,
            'instructions' => 'Add a project story for the Richmond garden gym, using the approved photography and client testimonial.',
        ]);
    }

    private function createBusinessProfileData(Website $website, User $owner): void
    {
        $profile = $website->businessProfileConnection()->create([
            'connected_by' => $owner->id,
            'account_name' => 'accounts/9900001',
            'location_name' => 'locations/9900001',
            'location_title' => 'Willow & Stone Garden Rooms',
            'access_token' => 'demo-business-profile-token',
            'refresh_token' => 'demo-business-profile-refresh-token',
            'access_token_expires_at' => now()->addHour(),
            'weekly_audits_enabled' => true,
            'weekly_posts_enabled' => true,
            'post_weekday' => 4,
            'post_hour' => 10,
            'timezone' => 'Europe/London',
            'brand_guidance' => 'Warm, design-led and approachable. Highlight craftsmanship and practical benefits. Never use pushy sales language.',
            'last_synced_at' => now()->subHours(8),
        ]);
        $audit = $profile->audits()->create([
            'status' => BusinessProfileAudit::STATUS_COMPLETED,
            'overall_status' => 'needs_attention',
            'snapshot' => ['title' => 'Willow & Stone Garden Rooms', 'primary_category' => 'Garden building supplier', 'photos' => 42, 'reviews' => 87, 'rating' => 4.8],
            'started_at' => now()->subDay(),
            'completed_at' => now()->subDay()->addMinutes(2),
        ]);
        $audit->recommendations()->createMany([
            ['key' => 'profile_description', 'severity' => 'warning', 'title' => 'Refresh the business description', 'description' => 'The description does not mention insulated garden offices or the current Surrey service area.', 'field_mask' => 'profile.description', 'current_value' => ['description' => 'Bespoke garden rooms, designed for you.'], 'proposed_value' => ['description' => 'Bespoke, fully insulated garden offices, studios and gyms designed and installed across Surrey by Willow & Stone.'], 'status' => BusinessProfileRecommendation::STATUS_PENDING],
            ['key' => 'special_hours', 'severity' => 'warning', 'title' => 'Add bank holiday hours', 'description' => 'Upcoming bank holiday opening hours have not been published.', 'field_mask' => 'specialHours', 'current_value' => [], 'proposed_value' => ['closed' => true], 'status' => BusinessProfileRecommendation::STATUS_PENDING],
            ['key' => 'photos', 'severity' => 'info', 'title' => 'Add recent project photography', 'description' => 'The newest completed garden studio is not yet represented in the profile gallery.', 'status' => BusinessProfileRecommendation::STATUS_DISMISSED],
        ]);
        $profile->posts()->createMany([
            ['status' => BusinessProfilePost::STATUS_PENDING_APPROVAL, 'topic' => 'Recent Richmond garden office', 'summary' => 'A calm, light-filled garden office designed for two. This Richmond project combines full insulation, bespoke storage and wide sliding doors for comfortable year-round working.', 'call_to_action_type' => 'LEARN_MORE', 'call_to_action_url' => 'https://'.self::DOMAIN.'/projects'],
            ['status' => BusinessProfilePost::STATUS_PUBLISHED, 'topic' => 'Free design consultations', 'summary' => 'Thinking about a garden room? Book a friendly design consultation and we will help you explore layouts, finishes and practical next steps.', 'call_to_action_type' => 'BOOK', 'call_to_action_url' => 'https://'.self::DOMAIN.'/contact', 'google_post_name' => 'locations/9900001/localPosts/42', 'approved_by' => $owner->id, 'approved_at' => now()->subWeek(), 'published_at' => now()->subWeek()],
        ]);
        $profile->reviews()->createMany([
            ['google_review_name' => 'locations/9900001/reviews/demo-1', 'reviewer_name' => 'Charlotte Turner', 'star_rating' => 5, 'comment' => 'From the first design meeting to the final handover, the team were brilliant. Our new office looks beautiful and is warm even on cold mornings.', 'reviewed_at' => now()->subDays(2), 'suggested_reply' => 'Thank you, Charlotte. We are delighted that your new office is working so well year-round. It was a pleasure bringing the design to life for you.', 'reply_status' => BusinessProfileReview::STATUS_PENDING_APPROVAL],
            ['google_review_name' => 'locations/9900001/reviews/demo-2', 'reviewer_name' => 'Tom Hughes', 'star_rating' => 4, 'comment' => 'Very happy with the finished studio and the quality is excellent. Communication around the delivery date could have been a little clearer.', 'reviewed_at' => now()->subDays(6), 'suggested_reply' => 'Thank you for the thoughtful feedback, Tom. We are so pleased you are happy with the studio, and we appreciate your honest note about delivery communication. We are reviewing that part of our process.', 'reply_status' => BusinessProfileReview::STATUS_PENDING_APPROVAL],
            ['google_review_name' => 'locations/9900001/reviews/demo-3', 'reviewer_name' => 'Rachel King', 'star_rating' => 5, 'comment' => 'Our garden gym has completely changed how we use the space. Great design, tidy installation and lovely people.', 'reviewed_at' => now()->subWeeks(3), 'google_reply' => 'Thank you, Rachel. We loved creating the gym for you and hope you enjoy it for many years.', 'suggested_reply' => 'Thank you, Rachel. We loved creating the gym for you and hope you enjoy it for many years.', 'reply_status' => BusinessProfileReview::STATUS_REPLIED, 'approved_by' => $owner->id, 'approved_at' => now()->subWeeks(3)->addDay(), 'replied_at' => now()->subWeeks(3)->addDay()],
        ]);
    }

    /** @return array<string, mixed> */
    private function check(string $category, string $key, string $label, string $status, string $message, array $details = []): array
    {
        return compact('category', 'key', 'label', 'status', 'message', 'details');
    }

    /** @return array<int, array<string, mixed>> */
    private function pageSpeedMetrics(): array
    {
        return [
            ['url' => 'https://'.self::DOMAIN.'/', 'strategy' => 'mobile', 'available' => true, 'performance_score' => 76, 'field' => ['available' => true, 'lcp_ms' => 2680, 'lcp_status' => 'needs_improvement', 'inp_ms' => 156, 'inp_status' => 'good', 'cls' => 0.06, 'cls_status' => 'good'], 'lab' => ['lcp_ms' => 2810, 'cls' => 0.05, 'tbt_ms' => 180, 'fcp_ms' => 1420, 'speed_index_ms' => 2960], 'recommendations' => [['title' => 'Serve gallery images in next-generation formats', 'savings_ms' => 620], ['title' => 'Defer non-critical JavaScript', 'savings_ms' => 280]]],
            ['url' => 'https://'.self::DOMAIN.'/', 'strategy' => 'desktop', 'available' => true, 'performance_score' => 94, 'field' => ['available' => true, 'lcp_ms' => 1840, 'lcp_status' => 'good', 'inp_ms' => 92, 'inp_status' => 'good', 'cls' => 0.03, 'cls_status' => 'good'], 'lab' => ['lcp_ms' => 1320, 'cls' => 0.02, 'tbt_ms' => 40, 'fcp_ms' => 710, 'speed_index_ms' => 1380], 'recommendations' => []],
        ];
    }
}
