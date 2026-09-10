<?php

use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\Form;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleMetric;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteRepository;
use App\Support\MembershipPlan;
use Illuminate\Support\Carbon;

it('prioritises the selected websites latest health and content work on the dashboard', function (): void {
    $user = User::factory()->create();
    $healthyWebsite = Website::factory()->for($user, 'owner')->create(['name' => 'Healthy website']);
    $warningWebsite = Website::factory()->for($user, 'owner')->create(['name' => 'Website needing work']);
    $user->update(['current_website_id' => $warningWebsite->id]);

    WebsiteHealthReport::factory()->for($healthyWebsite)->create([
        'overall_status' => 'healthy',
        'warning_checks' => 0,
        'failed_checks' => 0,
    ]);
    WebsiteHealthReport::factory()->for($warningWebsite)->create([
        'overall_status' => 'needs_attention',
        'warning_checks' => 3,
        'failed_checks' => 0,
        'checks' => [[
            'key' => 'meta_description',
            'label' => 'Meta description',
            'status' => 'warning',
            'message' => 'Your homepage needs a clear search description.',
        ]],
        'metrics' => ['changes' => ['new_issues' => 1, 'resolved_issues' => 2]],
    ]);
    ContentRequest::factory()->for($warningWebsite)->for($user, 'creator')->create([
        'instructions' => 'Refresh the services page introduction.',
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Website overview')
        ->assertSee('Website workspace')
        ->assertSee('data-mobile-nav-toggle', false)
        ->assertSee('data-mobile-nav', false)
        ->assertSee('Website needing work')
        ->assertSee('Website health')
        ->assertSee('Your homepage needs a clear search description.')
        ->assertSee('2 resolved')
        ->assertSee('1 new')
        ->assertSee('Refresh the services page introduction.')
        ->assertDontSee('Recent audit activity')
        ->assertDontSee('Workspace totals')
        ->assertDontSee('Form activity')
        ->assertDontSee('href="'.route('admin.forms.index').'"', false)
        ->assertDontSee('href="'.route('admin.form-submissions.index').'"', false);
});

it('shows the next site audit and content queue jobs', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create([
        'name' => 'Scheduled website',
        'health_reports_enabled' => true,
    ]);
    ContentPlan::factory()->for($website)->for($user, 'creator')->create([
        'enabled' => true,
        'weekday' => now('Europe/London')->addDay()->dayOfWeek,
        'hour' => 9,
    ]);

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertSuccessful()
        ->assertSee('Next health check')
        ->assertSee('Next preparation')
        ->assertSee('Scheduled website');
});

it('shows connected search performance and optional form activity for the selected website', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create(['name' => 'Connected website']);
    $connection = SearchConsoleConnection::factory()->for($website)->for($user, 'connector')->create(['property_url' => 'sc-domain:example.com']);
    SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([
        'month' => today()->startOfMonth(),
        'clicks' => 1234,
        'impressions' => 9876,
        'ctr' => 0.125,
        'position' => 8.4,
    ]);
    Form::factory()->for($website)->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Google Search Console')
        ->assertSee('1,234')
        ->assertSee('9,876')
        ->assertSee('12.5%')
        ->assertSee('Form activity')
        ->assertSee('Connected forms')
        ->assertSee('href="'.route('admin.form-submissions.index').'"', false);
});

it('compares the current and previous calendar months even when either import is missing', function (string $date, bool $hasCurrent, bool $hasPrevious): void {
    $this->travelTo(Carbon::parse($date));
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    $connection = SearchConsoleConnection::factory()->for($website)->for($user, 'connector')->create(['property_url' => 'sc-domain:example.com']);
    $currentMonth = today()->startOfMonth();
    $previousMonth = $currentMonth->copy()->subMonth();

    foreach ([[$currentMonth, $hasCurrent, 1234], [$previousMonth, $hasPrevious, 5678]] as [$month, $hasMetrics, $clicks]) {
        if ($hasMetrics) {
            SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([
                'month' => $month,
                'clicks' => $clicks,
                'impressions' => 20000,
                'ctr' => 0.125,
                'position' => 8.4,
            ]);
        }
    }

    SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([
        'month' => $currentMonth->copy()->subMonths(2),
        'clicks' => 999999,
    ]);
    SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([
        'month' => $currentMonth,
        'query' => 'unrelated query',
        'dimension_key' => hash('sha256', 'unrelated query'),
        'clicks' => 999999,
    ]);
    SearchConsoleMetric::factory()->for($website)->for($connection, 'connection')->create([
        'month' => $previousMonth,
        'property_url' => 'sc-domain:old.example.com',
        'property_hash' => hash('sha256', 'sc-domain:old.example.com'),
        'clicks' => 999999,
    ]);
    SearchConsoleMetric::factory()->create(['month' => $previousMonth, 'clicks' => 999999]);

    $response = $this->actingAs($user)->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSeeInOrder([$currentMonth->format('F Y').' · Month to date', $previousMonth->format('F Y').' · Previous month'])
        ->assertSee('This month is incomplete.')
        ->assertDontSee('999,999');

    if ($hasCurrent) {
        $response->assertSee('1,234');
    } else {
        $response->assertDontSee('1,234');
    }

    if ($hasPrevious) {
        $response->assertSee('5,678');
    } else {
        $response->assertDontSee('5,678');
    }

    if (! $hasCurrent || ! $hasPrevious) {
        $response->assertSee('No search performance imported for this month yet.');
    } else {
        $response->assertDontSee('No search performance imported for this month yet.');
    }
})->with([
    'both months at year rollover' => ['2026-01-01', true, true],
    'current month missing' => ['2026-09-01', false, true],
    'previous month missing at month end' => ['2026-03-31', true, false],
    'both months missing' => ['2026-09-01', false, false],
]);

it('shows active onboarding trial context on the website overview', function (): void {
    $user = User::factory()->create([
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    Website::factory()->for($user, 'owner')->create();

    $this->actingAs($user)
        ->get(route('admin.dashboard'))
        ->assertOk()
        ->assertSee('Your Growth trial is active')
        ->assertSee('Weekly health reports and SEO performance features are included during your trial.');
});

it('keeps forms and submissions inside the website workspace', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create(['name' => 'Client website']);
    Form::factory()->for($website)->create(['name' => 'Contact form']);

    $this->actingAs($user)
        ->get(route('admin.websites.show', $website))
        ->assertOk()
        ->assertSee('Health reports')
        ->assertSee('data-tab="content"', false)
        ->assertSee('data-tab-panel="content"', false)
        ->assertDontSee('Connect GitHub')
        ->assertDontSee('href="'.route('admin.github.connect', $website).'"', false)
        ->assertSee('Manual content requests')
        ->assertSee('>Forms</button>', false)
        ->assertSee('role="tablist"', false)
        ->assertSee('data-tab-panel="health"', false)
        ->assertSee('data-tab-panel="forms" hidden', false)
        ->assertDontSee('href="#health"', false)
        ->assertSee('Connect a website form')
        ->assertSee(route('forms.submit'))
        ->assertSee('name="_form_name"', false)
        ->assertSee('name="_honeypot"', false)
        ->assertSee('data-copy-target="form-onboarding-example"', false)
        ->assertSee('Contact form')
        ->assertSee('Submissions')
        ->assertDontSee('Recent submissions');
});

it('shows GitHub content tools only to administrators', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();

    $this->actingAs($admin)
        ->get(route('admin.websites.show', $website))
        ->assertOk()
        ->assertSee('data-tab="content"', false)
        ->assertSee('data-tab-panel="content"', false)
        ->assertSee('Manual content requests')
        ->assertSee('Change repository')
        ->assertSee('href="'.route('admin.website-repositories.create', $website).'"', false);
});

it('shows the content connection guidance while an administrator supports an unconnected website', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create();

    $this->actingAs($admin)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'content']))
        ->assertOk()
        ->assertSee('Choose how Sitewell prepares website changes')
        ->assertSee('Book a call with support');
});

it('hides GitHub content tools from non-administrators with connected repositories', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    WebsiteRepository::factory()->for($website)->create();

    $this->actingAs($user)
        ->get(route('admin.websites.show', $website))
        ->assertOk()
        ->assertDontSee('GitHub repository')
        ->assertDontSee('Choose how Sitewell prepares website changes')
        ->assertDontSee('Change repository')
        ->assertDontSee('href="'.route('admin.website-repositories.create', $website).'"', false);
});

it('guides Growth users to connect a content delivery option with specialist support', function (): void {
    $user = User::factory()->create([
        'membership_tier' => MembershipPlan::GROWTH,
        'onboarding_status' => 'trial_active',
        'onboarding_trial_ends_at' => now()->addDays(10),
    ]);
    $website = Website::factory()->for($user, 'owner')->create([
        'pixel_enabled' => true,
        'pixel_last_seen_at' => null,
        'wordpress_enabled' => true,
    ]);

    $this->actingAs($user)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'content']))
        ->assertOk()
        ->assertSee('Choose how Sitewell prepares website changes')
        ->assertSee('Sitewell Pixel')
        ->assertSee('WordPress')
        ->assertSee('GitHub')
        ->assertSee('Book a call with support')
        ->assertSee('href="'.route('admin.onboarding-call').'"', false);
});

it('shows the latest audit status on the websites index', function (): void {
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    WebsiteHealthReport::factory()->for($website)->create([
        'overall_status' => 'critical',
        'failed_checks' => 2,
    ]);

    $this->actingAs($user)
        ->get(route('admin.websites.index'))
        ->assertOk()
        ->assertSee('Latest audit')
        ->assertSee('Critical')
        ->assertSee('2 failed');
});
