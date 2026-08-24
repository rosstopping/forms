<?php

use App\Ai\Agents\ContentRequestPixelWriter;
use App\Enums\OptimisationStatus;
use App\Jobs\GenerateContentRequestPixelOptimisations;
use App\Jobs\GeneratePagePixelOptimisations;
use App\Jobs\StartCopilotRemediation;
use App\Models\ContentRequest;
use App\Models\GithubInstallation;
use App\Models\GithubUserAuthorization;
use App\Models\PixelPageSighting;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteRepository;
use App\Services\ContentRequestPixelOptimisationGenerator;
use App\Services\OptimisationDeploymentManager;
use App\Services\PixelUrlNormalizer;
use Illuminate\Support\Facades\Queue;

beforeEach(fn () => config(['forms.pixel_ui_enabled' => true]));

it('details live and reviewable changes on the pixel tab', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create(['pixel_enabled' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create(['url' => 'https://example.com/services']);
    $manager = app(OptimisationDeploymentManager::class);
    $live = $manager->create($website, $page, [
        'type' => 'title',
        'original_value' => 'Old services title',
        'new_value' => 'Live services title',
    ], $admin);
    $manager->approve($live);
    $manager->deploy($live->refresh(), $admin);
    $manager->create($website, $page, [
        'type' => 'meta_description',
        'original_value' => null,
        'new_value' => 'A reviewable description.',
    ], $admin);

    $this->actingAs($admin)
        ->get(route('admin.websites.show', ['website' => $website, 'tab' => 'pixel']))
        ->assertSuccessful()
        ->assertSee('Live Pixel changes')
        ->assertSee('Live services title')
        ->assertSee('Pixel drafts awaiting review')
        ->assertSee('A reviewable description.')
        ->assertSee('Open live page')
        ->assertSee('Rollback');
});

it('uses one report action to queue both pixel and github remediation', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->for($admin, 'owner')->create(['pixel_enabled' => true]);
    $installation = GithubInstallation::factory()->create(['installed_by' => $admin->id]);
    WebsiteRepository::factory()->for($website)->create(['github_installation_id' => $installation->id]);
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'checks' => [['category' => 'security', 'key' => 'headers', 'label' => 'Headers', 'status' => 'warning', 'message' => 'Missing header.']],
    ]);
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'checks' => [['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'Missing description.']],
    ]);

    $this->actingAs($admin)
        ->post(route('admin.report-remediation.store', [$website, $report]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Pixel drafts queued for 1 page. GitHub remediation queued for review.');

    Queue::assertPushed(fn (GeneratePagePixelOptimisations $job): bool => $job->page->is($page));
    Queue::assertPushed(StartCopilotRemediation::class, 1);
    expect(RemediationRun::query()->whereBelongsTo($report, 'report')->exists())->toBeTrue();
});

it('turns an eligible pending content todo into a reviewable pixel draft', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create(['pixel_enabled' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create(['completed_at' => now()]);
    $page = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'title' => 'Services',
        'meta_description' => null,
    ]);
    PixelPageSighting::factory()->for($website)->create([
        'url' => $page->url,
        'url_hash' => app(PixelUrlNormalizer::class)->hash($page->url),
    ]);
    $contentRequest = ContentRequest::factory()->for($website)->for($admin, 'creator')->create([
        'instructions' => 'Improve the services page metadata without adding new claims.',
    ]);
    ContentRequestPixelWriter::fake([[
        'changes' => [[
            'url' => $page->url,
            'type' => 'meta_description',
            'value' => 'Explore the services available from Example.',
            'reason' => 'Adds missing page metadata.',
        ]],
    ]]);

    (new GenerateContentRequestPixelOptimisations($contentRequest, $admin))
        ->handle(app(ContentRequestPixelOptimisationGenerator::class));

    $optimisation = $contentRequest->optimisations()->sole();

    expect($optimisation->status)->toBe(OptimisationStatus::Draft)
        ->and($optimisation->currentVersion->new_value)->toBe('Explore the services available from Example.')
        ->and($contentRequest->fresh()->pixel_processed_at)->not->toBeNull()
        ->and($contentRequest->fresh()->picked_up_at)->toBeNull();
});

it('queues existing pending content todos from the pixel tab', function (): void {
    Queue::fake();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $website = Website::factory()->for($admin, 'owner')->create(['pixel_enabled' => true]);
    $contentRequest = ContentRequest::factory()->for($website)->for($admin, 'creator')->create();

    $this->actingAs($admin)
        ->post(route('admin.websites.pixel.content-requests.store', $website))
        ->assertRedirect(route('admin.websites.show', ['website' => $website, 'tab' => 'pixel']));

    Queue::assertPushed(fn (GenerateContentRequestPixelOptimisations $job): bool => $job->contentRequest->is($contentRequest));
});
