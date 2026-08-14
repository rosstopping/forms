<?php

use App\Enums\OptimisationStatus;
use App\Models\Optimisation;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;

beforeEach(fn () => config(['forms.pixel_ui_enabled' => true]));

/** @return array{User, Website, WebsiteHealthReport, WebsiteHealthReportPage} */
function optimisationPageWorkspace(): array
{
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services/roof-repairs',
        'url_hash' => hash('sha256', 'https://example.com/services/roof-repairs'),
        'title' => 'Roof Repairs',
        'meta_description' => 'Existing description',
        'checks' => [[
            'key' => 'page_title',
            'label' => 'Page title',
            'status' => 'warning',
            'message' => 'The title could be improved.',
        ]],
    ]);

    return [$owner, $website, $report, $page];
}

it('links crawled report pages to the optimisation workspace', function (): void {
    [$owner, $website, $report, $page] = optimisationPageWorkspace();

    $this->actingAs($owner)
        ->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()
        ->assertSee(route('admin.website-health-report-pages.show', [$website, $report, $page]));

    $this->actingAs($owner)
        ->get(route('admin.website-health-report-pages.show', [$website, $report, $page]))
        ->assertSuccessful()
        ->assertSee('Current crawled values')
        ->assertSee('The title could be improved.')
        ->assertSee('Create optimisation');
});

it('creates approves deploys and rolls back a structured optimisation', function (): void {
    [$owner, $website, $report, $page] = optimisationPageWorkspace();
    $workspace = route('admin.website-health-report-pages.show', [$website, $report, $page]);

    $this->actingAs($owner)
        ->post(route('admin.optimisations.store', [$website, $report, $page]), [
            'type' => 'title',
            'target_description' => 'Browser and search result title',
            'original_value' => 'Roof Repairs',
            'new_value' => 'Roof Repairs in Doncaster',
        ])
        ->assertRedirect($workspace);

    $optimisation = Optimisation::query()->sole();
    expect($optimisation->status)->toBe(OptimisationStatus::Draft)
        ->and($optimisation->currentVersion->new_value)->toBe('Roof Repairs in Doncaster');

    $this->actingAs($owner)
        ->post(route('admin.optimisations.deploy', [$website, $report, $page, $optimisation]))
        ->assertRedirect($workspace);

    expect($optimisation->refresh()->status)->toBe(OptimisationStatus::Deployed)
        ->and($optimisation->approved_at)->not->toBeNull()
        ->and($optimisation->deployed_at)->not->toBeNull();

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => $page->url,
    ]))->assertSuccessful()
        ->assertJsonPath('changes.0.value', 'Roof Repairs in Doncaster');

    $this->actingAs($owner)
        ->get($workspace)
        ->assertSuccessful()
        ->assertSee('Live via Pixel')
        ->assertSee('Roof Repairs in Doncaster')
        ->assertSee('Version and deployment history');

    $this->actingAs($owner)
        ->post(route('admin.optimisations.rollback', [$website, $report, $page, $optimisation]))
        ->assertRedirect($workspace);

    expect($optimisation->refresh()->status)->toBe(OptimisationStatus::RolledBack)
        ->and($optimisation->deployments()->count())->toBe(2);

    $this->getJson(route('pixel.payload', [
        'siteKey' => $website->pixel_public_key,
        'url' => $page->url,
    ]))->assertSuccessful()->assertJsonCount(0, 'changes');
});

it('creates a new immutable version after rollback', function (): void {
    [$owner, $website, $report, $page] = optimisationPageWorkspace();
    $this->actingAs($owner)->post(route('admin.optimisations.store', [$website, $report, $page]), [
        'type' => 'title',
        'original_value' => 'Roof Repairs',
        'new_value' => 'Roof Repairs in Doncaster',
    ]);
    $optimisation = Optimisation::query()->sole();
    $this->actingAs($owner)->post(route('admin.optimisations.deploy', [$website, $report, $page, $optimisation]));
    $this->actingAs($owner)->post(route('admin.optimisations.rollback', [$website, $report, $page, $optimisation]));

    $this->actingAs($owner)
        ->post(route('admin.optimisation-versions.store', [$website, $report, $page, $optimisation]), [
            'new_value' => 'Emergency Roof Repairs in Doncaster',
        ])
        ->assertRedirect();

    expect($optimisation->versions()->orderBy('version')->pluck('new_value')->all())->toBe([
        'Roof Repairs in Doncaster',
        'Emergency Roof Repairs in Doncaster',
    ])->and($optimisation->deployments()->first()->version->version)->toBe(1);
});

it('requires manager access and exact nested ownership for mutations', function (): void {
    [$owner, $website, $report, $page] = optimisationPageWorkspace();
    $outsider = User::factory()->create();
    $otherReport = WebsiteHealthReport::factory()->for($website)->create();

    $this->actingAs($outsider)
        ->post(route('admin.optimisations.store', [$website, $report, $page]), [
            'type' => 'title',
            'new_value' => 'Forbidden',
        ])->assertForbidden();

    $this->actingAs($owner)
        ->post(route('admin.optimisations.store', [$website, $otherReport, $page]), [
            'type' => 'title',
            'new_value' => 'Wrong scope',
        ])->assertNotFound();

    expect(Optimisation::query()->count())->toBe(0);
});
