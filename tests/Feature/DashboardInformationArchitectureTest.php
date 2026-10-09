<?php

use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\Form;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteRepository;
use App\Services\ReportingPeriod;
use App\Support\MembershipPlan;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

it('loads recent content runs on the Search page without sorting large prompts', function (): void {
    $user = User::factory()->create(['membership_tier' => MembershipPlan::GROWTH, 'membership_status' => 'active']);
    $website = Website::factory()->for($user, 'owner')->create();
    $plan = ContentPlan::factory()->for($website)->for($user, 'creator')->create();
    $repository = WebsiteRepository::factory()->for($website)->create();
    $generationIds = [];

    foreach (range(11, 1) as $daysAgo) {
        $generationIds[] = ContentGeneration::factory()->for($plan, 'plan')->for($repository, 'repository')->for($user, 'requester')->create([
            'scheduled_for' => today()->subDays($daysAgo),
            'created_at' => now()->subDays($daysAgo),
            'prompt' => str_repeat('Long prompt. ', 2500),
        ])->id;
    }

    $queries = [];
    DB::listen(function (QueryExecuted $query) use (&$queries): void {
        if (str_contains($query->sql, 'content_generations')) {
            $queries[] = $query->sql;
        }
    });

    $this->actingAs($user)->get(route('admin.websites.section', [$website, 'search']))
        ->assertSuccessful()
        ->assertViewHas('website', fn (Website $loaded): bool => $loaded->contentPlan->generations->pluck('id')->all() === array_reverse(array_slice($generationIds, -8)));

    expect(implode(' ', $queries))->not->toContain('row_number()')
        ->and(implode(' ', $queries))->not->toContain('`prompt`');
});

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
        ->assertSee('href="'.route('admin.form-submissions.index').'"', false);
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
    SearchConsoleDailyMetric::factory()->for($website)->create([
        'search_console_connection_id' => $connection->id,
        'property_url' => $connection->property_url,
        'date' => ReportingPeriod::cutoff()->toDateString(),
        'clicks' => 1234,
        'impressions' => 9876,
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

it('compares rolling periods even when either daily import is missing', function (string $date, bool $hasCurrent, bool $hasPrevious): void {
    $this->travelTo(Carbon::parse($date));
    $user = User::factory()->create();
    $website = Website::factory()->for($user, 'owner')->create();
    $connection = SearchConsoleConnection::factory()->for($website)->for($user, 'connector')->create(['property_url' => 'sc-domain:example.com']);
    $period = ReportingPeriod::fromInput([]);
    foreach ([[$period->end, $hasCurrent, 1234], [$period->previousEnd, $hasPrevious, 5678]] as [$day, $hasMetrics, $clicks]) {
        if ($hasMetrics) {
            SearchConsoleDailyMetric::factory()->for($website)->create([
                'search_console_connection_id' => $connection->id, 'property_url' => $connection->property_url,
                'date' => $day->toDateString(), 'clicks' => $clicks, 'impressions' => 20000, 'position' => 8.4,
            ]);
        }
    }
    SearchConsoleDailyMetric::factory()->for($website)->create([
        'search_console_connection_id' => $connection->id, 'property_url' => 'sc-domain:old.example.com',
        'date' => $period->end->toDateString(), 'clicks' => 999999,
    ]);
    SearchConsoleDailyMetric::factory()->create(['clicks' => 999999]);
    $response = $this->actingAs($user)->get(route('admin.dashboard'))
        ->assertOk()->assertSee('Last 28 days')->assertSeeInOrder(['Selected period', 'Comparison period'])
        ->assertSee('Partial coverage')->assertDontSee('999,999');
    $hasCurrent ? $response->assertSee('1,234') : $response->assertDontSee('1,234');
    $hasPrevious ? $response->assertSee('5,678') : $response->assertDontSee('5,678');
    if (! $hasCurrent || ! $hasPrevious) {
        $response->assertSee('No daily search performance imported for this period yet.');
    }
})->with([
    'both periods at year rollover' => ['2026-01-01', true, true],
    'current period missing' => ['2026-09-01', false, true],
    'previous period missing at month end' => ['2026-03-31', true, false],
    'both periods missing' => ['2026-09-01', false, false],
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
        ->assertSee('Content connections')
        ->assertSee('href="'.route('admin.websites.section', [$website, 'forms']).'"', false)
        ->assertSee('aria-label="Website sections"', false)
        ->assertSee('data-tab-panel="health"', false)
        ->assertSee('data-tab-panel="forms"', false)
        ->assertDontSee('href="#health"', false)
        ->assertSee('Connect a website form')
        ->assertSee(route('forms.submit'))
        ->assertSee('name="_form_name"', false)
        ->assertSee('name="_honeypot"', false)
        ->assertSee('data-copy-target="form-onboarding-example"', false)
        ->assertSee('Contact form')
        ->assertSee('Team notifications')
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
        ->assertSee('Content queue')
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
