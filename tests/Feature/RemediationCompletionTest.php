<?php

use App\Jobs\SyncCopilotRemediation;
use App\Models\GithubUserAuthorization;
use App\Models\RemediationRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteRepository;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use Illuminate\Support\Facades\Http;

it('keeps prepare fixes visible and lets admins dismiss an already finished pull request', function (): void {
    Http::preventStrayRequests();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($admin)->create();
    $website = Website::factory()->create(['pixel_enabled' => false]);
    $repository = WebsiteRepository::factory()->for($website)->create();
    $report = WebsiteHealthReport::factory()->for($website)->create([
        'checks' => [['category' => 'seo', 'key' => 'page_title', 'label' => 'Page title', 'status' => 'failed', 'message' => 'Missing title.']],
    ]);
    $run = RemediationRun::factory()->for($report, 'report')->for($repository, 'repository')->create([
        'status' => RemediationRun::STATUS_PULL_REQUEST_OPEN,
        'pull_request_url' => 'https://github.com/example/site/pull/123',
    ]);
    $this->actingAs($admin)->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()->assertSee('Prepare fixes')->assertSee('Pull request open')->assertSee('Mark as complete');

    $this->patch(route('admin.remediation-runs.complete', [$website, $report, $run]))
        ->assertRedirect(route('admin.website-health-reports.show', [$website, $report]));
    expect($run->fresh()->status)->toBe(RemediationRun::STATUS_COMPLETED)
        ->and($run->fresh()->completed_at)->not->toBeNull()
        ->and($run->fresh()->merged_at)->toBeNull();
    $this->get(route('admin.website-health-reports.show', [$website, $report]))
        ->assertSuccessful()->assertSee('Prepare fixes')->assertDontSee('Pull request open')->assertDontSee('View pull request')->assertDontSee('Mark as complete');
    $this->get(route('admin.overview'))->assertViewHas('remediationReviews', fn ($items): bool => $items->total() === 0);

    $copilot = Mockery::mock(CopilotAgentClient::class);
    $github = Mockery::mock(GithubAppClient::class);
    $copilot->shouldNotReceive('task');
    (new SyncCopilotRemediation($run))->handle($copilot, $github);
    expect($run->fresh()->status)->toBe(RemediationRun::STATUS_COMPLETED);
    Http::assertNothingSent();
});

it('restricts completion to admins and the matching website and report', function (): void {
    $run = RemediationRun::factory()->create(['status' => RemediationRun::STATUS_PULL_REQUEST_OPEN]);
    $report = $run->report;
    $website = $report->website;
    $this->actingAs($website->owner)->patch(route('admin.remediation-runs.complete', [$website, $report, $run]))->assertForbidden();
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $otherReport = WebsiteHealthReport::factory()->for($website)->create();
    $this->actingAs($admin)->patch(route('admin.remediation-runs.complete', [$website, $otherReport, $run]))->assertNotFound();
    $otherWebsite = Website::factory()->create();
    $this->patch(route('admin.remediation-runs.complete', [$otherWebsite, $report, $run]))->assertNotFound();
    expect($run->fresh()->status)->toBe(RemediationRun::STATUS_PULL_REQUEST_OPEN);
});
