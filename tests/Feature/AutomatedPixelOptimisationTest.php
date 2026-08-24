<?php

use App\Ai\Agents\PixelOptimisationWriter;
use App\Enums\OptimisationStatus;
use App\Models\Optimisation;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use Laravel\Ai\Prompts\AgentPrompt;
use RuntimeException;

beforeEach(fn () => config(['forms.pixel_ui_enabled' => true]));

function automatedOptimisationWorkspace(): array
{
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($owner, 'owner')->create(['name' => 'Example Roofing']);
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/roof-repairs',
        'title' => 'Roof Repairs',
        'meta_description' => null,
        'checks' => [
            ['key' => 'page_title', 'label' => 'Page title', 'status' => 'warning', 'message' => 'The title could be improved.'],
            ['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'No meta description was found.'],
        ],
    ]);

    return [$owner, $website, $report, $page];
}

it('generates structured pixel drafts from crawl evidence without deploying them', function () {
    [$owner, $website, $report, $page] = automatedOptimisationWorkspace();
    PixelOptimisationWriter::fake([[
        'changes' => [
            ['type' => 'title', 'value' => 'Roof Repairs | Example Roofing', 'reason' => 'Clarifies the page topic and brand.'],
            ['type' => 'meta_description', 'value' => 'Learn about roof repair services from Example Roofing.', 'reason' => 'Adds the missing search description.'],
        ],
    ]])->preventStrayPrompts();

    $this->actingAs($owner)
        ->post(route('admin.optimisations.generate', [$website, $report, $page]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Sitewell generated 2 fixes for approval.');

    expect($page->optimisations()->count())->toBe(2)
        ->and($page->optimisations()->where('status', OptimisationStatus::Draft)->count())->toBe(2)
        ->and($page->optimisations()->with('currentVersion')->get()->pluck('currentVersion.original_value')->all())
        ->toContain('Roof Repairs', null);

    PixelOptimisationWriter::assertPrompted(fn (AgentPrompt $prompt): bool => $prompt->contains('Example Roofing') && $prompt->contains('No meta description was found.'));
});

it('discards unsafe unsupported oversized duplicate and unchanged AI output', function () {
    [$owner, $website, $report, $page] = automatedOptimisationWorkspace();
    Optimisation::factory()->for($website)->for($page, 'page')->create(['type' => 'title', 'status' => OptimisationStatus::Draft]);
    PixelOptimisationWriter::fake([[
        'changes' => [
            ['type' => 'title', 'value' => 'A different title', 'reason' => 'Duplicate'],
            ['type' => 'meta_description', 'value' => str_repeat('x', 171), 'reason' => 'Too long'],
            ['type' => 'html', 'value' => '<script>alert(1)</script>', 'reason' => 'Unsupported'],
        ],
    ]]);

    $this->actingAs($owner)
        ->post(route('admin.optimisations.generate', [$website, $report, $page]))
        ->assertSessionHas('status', 'Sitewell found no safe new Pixel fixes for this page.');

    expect($page->optimisations()->count())->toBe(1);
});

it('deploys every reviewed page fix through one explicit approval action', function () {
    [$owner, $website, $report, $page] = automatedOptimisationWorkspace();
    PixelOptimisationWriter::fake([[
        'changes' => [
            ['type' => 'title', 'value' => 'Roof Repairs | Example Roofing', 'reason' => 'Title fix'],
            ['type' => 'meta_description', 'value' => 'Learn about roof repair services from Example Roofing.', 'reason' => 'Description fix'],
        ],
    ]]);

    $this->actingAs($owner)->post(route('admin.optimisations.generate', [$website, $report, $page]));
    $this->actingAs($owner)
        ->post(route('admin.optimisations.deploy-page', [$website, $report, $page]))
        ->assertRedirect()
        ->assertSessionHas('status', '2 approved Pixel fixes are now live.');

    expect($page->optimisations()->where('status', OptimisationStatus::Deployed)->count())->toBe(2)
        ->and($page->optimisations()->withCount('deployments')->get()->pluck('deployments_count')->all())->toBe([1, 1])
        ->and($website->fresh()->pixel_payload_version)->toBe(3);
});

it('only lets website managers generate and deploy automated fixes', function () {
    [, $website, $report, $page] = automatedOptimisationWorkspace();
    $outsider = User::factory()->create();
    PixelOptimisationWriter::fake();

    $this->actingAs($outsider)
        ->post(route('admin.optimisations.generate', [$website, $report, $page]))
        ->assertForbidden();
    $this->actingAs($outsider)
        ->post(route('admin.optimisations.deploy-page', [$website, $report, $page]))
        ->assertForbidden();

    PixelOptimisationWriter::assertNeverPrompted();
});

it('fails safely without creating or deploying changes when AI is unavailable', function () {
    [$owner, $website, $report, $page] = automatedOptimisationWorkspace();
    PixelOptimisationWriter::fake(fn () => throw new RuntimeException('Provider unavailable'));

    $this->actingAs($owner)
        ->post(route('admin.optimisations.generate', [$website, $report, $page]))
        ->assertRedirect()
        ->assertSessionHas('error', 'Sitewell could not generate fixes right now. No changes were created or deployed.');

    expect($page->optimisations()->count())->toBe(0);
});

it('presents AI generation as the primary workflow and keeps manual entry advanced', function () {
    [$owner, $website, $report, $page] = automatedOptimisationWorkspace();

    $this->actingAs($owner)
        ->get(route('admin.website-health-report-pages.show', [$website, $report, $page]))
        ->assertSuccessful()
        ->assertSee('Generate fixes with AI')
        ->assertSee('Advanced: Create optimisation manually');
});
