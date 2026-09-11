<?php

use App\Enums\DeploymentMethod;
use App\Enums\OptimisationStatus;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\GithubUserAuthorization;
use App\Models\Optimisation;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteRepository;
use App\Support\WebsiteNavigation;
use Illuminate\Support\Facades\Http;

it('shows cross-site schedules and pending reviews without changing the selected website', function (): void {
    Http::preventStrayRequests();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $selected = Website::factory()->create();
    $other = Website::factory()->create(['health_reports_enabled' => true]);
    $admin->update(['current_website_id' => $selected->id]);
    $report = WebsiteHealthReport::factory()->for($other)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create();
    $draft = Optimisation::factory()->for($other)->for($page, 'page')->create();
    Optimisation::factory()->for($selected)->create(['status' => OptimisationStatus::PendingApproval]);
    Optimisation::factory()->for($other)->create(['status' => OptimisationStatus::Deployed]);
    $plan = ContentPlan::factory()->for($other)->create(['enabled' => false]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN]);
    ContentGeneration::factory()->for($plan, 'plan')->create(['status' => ContentGeneration::STATUS_COMPLETED, 'scheduled_for' => today()->subDay()]);
    RemediationRun::factory()->for($report, 'report')->create(['status' => RemediationRun::STATUS_PULL_REQUEST_OPEN]);

    $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertSee('Upcoming schedule')
        ->assertSee('Actions to review')
        ->assertSee($other->name)
        ->assertSee('href="'.route('admin.website-health-report-pages.show', [$other, $report, $page]).'"', false)
        ->assertViewHas('approvalCount', 4)
        ->assertViewHas('optimisations', fn ($items): bool => $items->total() === 2 && $items->contains('id', $draft->id))
        ->assertViewHas('automationSchedule', fn ($items): bool => $items->count() === 1 && $items->first()['website']->is($other));

    expect($admin->fresh()->current_website_id)->toBe($selected->id);
    Http::assertNothingSent();
});

it('restricts the admin overview and its navigation to administrators', function (): void {
    $this->get(route('admin.overview'))->assertRedirect(route('login'));
    $user = User::factory()->create();
    $this->actingAs($user)->get(route('admin.overview'))->assertForbidden();
    $this->get(route('admin.dashboard'))->assertDontSee('href="'.route('admin.overview').'"', false);
});

it('renders an empty admin overview with desktop and mobile navigation', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $response = $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertSee('No upcoming automation is scheduled.')
        ->assertSee('No draft changes awaiting review.')
        ->assertViewHas('approvalCount', 0);

    expect(substr_count($response->getContent(), 'href="'.route('admin.overview').'"'))->toBe(2);
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $userMenu = $xpath->query('//aside/*[@data-desktop-user-menu]')->item(0);
    expect($userMenu)->not->toBeNull();
    foreach (['fixed', 'bottom-0', 'left-0', 'w-[17rem]'] as $class) {
        expect(explode(' ', $userMenu->getAttribute('class')))->toContain($class);
    }
    expect($xpath->query('.//a', $userMenu)->item(0)->getAttribute('href'))->toBe(route('admin.profile.edit'));
    expect($xpath->query('.//form', $userMenu)->item(0)->getAttribute('action'))->toBe(route('logout'));
    $navigationLinks = (new DOMXPath($document))->query('//nav/p[normalize-space()="Administration"]/following-sibling::*[1]/self::a | //nav/p[normalize-space()="Administration"]/following-sibling::*[1]/a[1]');
    expect($navigationLinks->length)->toBe(2);
    foreach ($navigationLinks as $link) {
        expect($link->getAttribute('href'))->toBe(route('admin.overview'))
            ->and(trim($link->textContent))->toBe('Dashboard')
            ->and($link->getElementsByTagName('svg')->length)->toBe(1);
    }

});

it('orders eligible health and content runs and excludes inactive or expired websites', function (): void {
    $this->travelTo(now()->setDate(2026, 9, 14)->setTime(12, 0));
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->create(['health_reports_enabled' => true]);
    WebsiteRepository::factory()->for($website)->create();
    ContentPlan::factory()->for($website)->for($admin, 'creator')->create(['weekday' => 2, 'hour' => 9]);
    Website::factory()->create(['is_active' => false, 'health_reports_enabled' => true]);
    $expired = User::factory()->create(['membership_status' => 'cancelled', 'membership_current_period_end' => now()->subDay()]);
    Website::factory()->for($expired, 'owner')->create(['health_reports_enabled' => true]);

    $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertViewHas('automationSchedule', function ($items) use ($website): bool {
            return $items->pluck('type')->all() === ['Health report', 'Content queue']
                && $items->every(fn ($item): bool => $item['website']->is($website))
                && $items->first()['next_run_at']->lessThan($items->last()['next_run_at']);
        });
});

it('groups website sections directly beneath Overview in both navigation menus', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->create();
    $response = $this->actingAs($admin)->get(route('admin.overview'))->assertSuccessful();
    $document = new DOMDocument;
    @$document->loadHTML($response->getContent());
    $xpath = new DOMXPath($document);
    $headings = $xpath->query('//*[@data-website-navigation-heading]');
    expect($headings->length)->toBe(2);
    foreach ($headings as $heading) {
        expect(trim($heading->textContent))->toBe($website->name);
    }
    $groups = $xpath->query('//*[@data-website-navigation]');
    expect($groups->length)->toBe(2);

    foreach ($groups as $group) {
        $overview = $xpath->query('preceding-sibling::*[1]', $group)->item(0);
        expect($overview->getAttribute('href'))->toBe(route('admin.dashboard'));
        $links = $xpath->query('.//a', $group);
        $urls = array_map(fn ($link): string => $link->getAttribute('href'), iterator_to_array($links));
        foreach (['health', 'search', 'seo', 'content', 'forms', 'business-profile', 'settings'] as $section) {
            expect($urls)->toContain(WebsiteNavigation::routeFor($website, $section));
        }
        expect($urls)->not->toContain(route('admin.users.index'), route('admin.prospects.index'));
    }
});

it('excludes disabled-site Pixel reviews while retaining other deployment methods', function (OptimisationStatus $status): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $disabledWebsite = Website::factory()->create(['pixel_enabled' => false]);
    $enabledWebsite = Website::factory()->create(['pixel_enabled' => true]);
    $hidden = Optimisation::factory()->for($disabledWebsite)->create([
        'status' => $status,
        'deployment_method' => DeploymentMethod::Pixel,
        'url' => 'https://disabled.example/hidden-pixel-review',
    ]);
    $visible = Optimisation::factory()->for($enabledWebsite)->create([
        'status' => $status,
        'deployment_method' => DeploymentMethod::Pixel,
    ]);
    $otherChanges = collect([DeploymentMethod::Manual, DeploymentMethod::Github, DeploymentMethod::Wordpress])
        ->map(fn (DeploymentMethod $method): Optimisation => Optimisation::factory()->for($disabledWebsite)->create([
            'status' => $status,
            'deployment_method' => $method,
        ]));

    $this->actingAs($admin)->get(route('admin.overview'))
        ->assertSuccessful()
        ->assertDontSee($hidden->url)
        ->assertViewHas('approvalCount', 4)
        ->assertViewHas('optimisations', fn ($items): bool => $items->total() === 4
            && $items->contains('id', $visible->id)
            && ! $items->contains('id', $hidden->id)
            && $otherChanges->every(fn (Optimisation $change): bool => $items->contains('id', $change->id)));
})->with([OptimisationStatus::Draft, OptimisationStatus::PendingApproval]);
