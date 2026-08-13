<?php

use App\Ai\Agents\PixelOptimisationWriter;
use App\Enums\OptimisationStatus;
use App\Jobs\GeneratePagePixelOptimisations;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Services\OptimisationDeploymentManager;
use App\Services\PixelOptimisationGenerator;
use Illuminate\Support\Facades\Queue;

function reportPixelWorkspace(): array
{
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create(['name' => 'Example Ltd']);
    $website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $report = WebsiteHealthReport::factory()->for($website)->create();
    $eligible = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/services',
        'meta_description' => null,
        'checks' => [['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'warning', 'message' => 'No meta description was found.']],
    ]);
    $healthy = WebsiteHealthReportPage::factory()->for($report, 'report')->create([
        'url' => 'https://example.com/contact',
        'checks' => [['key' => 'meta_description', 'label' => 'Meta description', 'status' => 'passed', 'message' => 'Meta description is present.']],
    ]);

    return [$owner, $website, $report, $eligible, $healthy];
}

it('queues automated generation only for report pages with eligible issues', function () {
    Queue::fake();
    [$owner, $website, $report, $eligible] = reportPixelWorkspace();

    $this->actingAs($owner)
        ->post(route('admin.report-optimisations.generate', [$website, $report]))
        ->assertRedirect()
        ->assertSessionHas('status', 'Sitewell queued AI fixes for 1 page.');

    Queue::assertPushed(GeneratePagePixelOptimisations::class, 1);
    Queue::assertPushed(fn (GeneratePagePixelOptimisations $job): bool => $job->page->is($eligible) && $job->author->is($owner));
});

it('generates the queued page draft and leaves it awaiting approval', function () {
    [$owner, , , $eligible] = reportPixelWorkspace();
    PixelOptimisationWriter::fake([[
        'changes' => [['type' => 'meta_description', 'value' => 'Discover services from Example Ltd.', 'reason' => 'Adds the missing description.']],
    ]]);

    (new GeneratePagePixelOptimisations($eligible, $owner))->handle(app(PixelOptimisationGenerator::class));

    expect($eligible->optimisations()->count())->toBe(1)
        ->and($eligible->optimisations()->first()->status)->toBe(OptimisationStatus::Draft);
});

it('approves and deploys all reviewed fixes belonging to the report', function () {
    [$owner, $website, $report, $eligible] = reportPixelWorkspace();
    $manager = app(OptimisationDeploymentManager::class);
    $manager->create($website, $eligible, [
        'type' => 'meta_description',
        'original_value' => null,
        'new_value' => 'Discover services from Example Ltd.',
    ], $owner);

    $this->actingAs($owner)
        ->post(route('admin.report-optimisations.deploy', [$website, $report]))
        ->assertRedirect()
        ->assertSessionHas('status', '1 approved Pixel fix is now live.');

    expect($eligible->optimisations()->first()->status)->toBe(OptimisationStatus::Deployed);
});

it('shows bulk Pixel remediation and recommendations only for pages with issues', function () {
    [$owner, $website, $report, $eligible, $healthy] = reportPixelWorkspace();

    $response = $this->actingAs($owner)->get(route('admin.website-health-reports.show', [$website, $report]));

    $response->assertSuccessful()
        ->assertSee('Prepare Pixel fixes')
        ->assertSee('Generate all Pixel fixes')
        ->assertSee(route('admin.website-health-report-pages.show', [$website, $report, $eligible]))
        ->assertDontSee(route('admin.website-health-report-pages.show', [$website, $report, $healthy]));
});

it('forbids outsiders from bulk generation and deployment', function () {
    [, $website, $report] = reportPixelWorkspace();
    $outsider = User::factory()->create();

    $this->actingAs($outsider)->post(route('admin.report-optimisations.generate', [$website, $report]))->assertForbidden();
    $this->actingAs($outsider)->post(route('admin.report-optimisations.deploy', [$website, $report]))->assertForbidden();
});
