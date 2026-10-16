<?php

use App\Jobs\StartContentGeneration;
use App\Jobs\SyncContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\SeoTargetKeyword;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\ContentBudget;
use App\Services\ContentGenerationNotifier;
use App\Services\ContentNoWorkRecovery;
use App\Services\ContentSchedule;
use App\Services\ContentWorkSelector;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 10, 12)->setTime(8, 0));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $owner = User::factory()->create(['membership_tier' => 'growth']);
    $this->website = Website::factory()->for($owner, 'owner')->create();
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->plan = ContentPlan::factory()->for($this->website)->for($this->admin, 'creator')->create();
    $this->generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create([
        'trigger' => 'scheduled', 'engine' => 'copilot', 'status' => 'running', 'started_at' => now()->subMinutes(10),
        'copilot_task_state' => 'completed', 'copilot_task_id' => '11111111-1111-4111-8111-111111111111', 'budget_reserved_at' => now(), 'work_units' => ['articles' => 1, 'optimisations' => 0],
    ]);
    $this->request = ContentRequest::factory()->for($this->website)->for($this->generation, 'generation')->create([
        'instructions' => 'Create the original client guide', 'work_type' => 'new_article', 'picked_up_at' => now()->subMinutes(10),
    ]);
    $this->next = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Improve an independent service page', 'work_type' => 'optimisation']);
});

function contentResultDetails(WebsiteRepository $repository, int $files = 0): array
{
    return ['pull_request' => ['number' => 42, 'html_url' => 'https://github.com/'.$repository->full_name.'/pull/42',
        'base' => ['repo' => ['id' => $repository->repository_id], 'ref' => $repository->default_branch, 'sha' => 'base'],
        'head' => ['repo' => ['id' => $repository->repository_id], 'ref' => 'copilot/content', 'sha' => 'head'],
        'state' => 'open', 'changed_files' => $files, 'additions' => $files * 10, 'deletions' => 0, 'merged_at' => null,
        'body' => 'Required integration file changed recently. No eligible changes.'],
        'files' => $files ? [['filename' => 'guide.md', 'additions' => 10]] : []];
}

function noWorkEvidence(): array
{
    return ['classification' => 'no_work', 'source' => 'github_pull_request', 'changed_files' => 0];
}

it('recognizes empty PRs for terminal tasks and starts at most one replacement', function (string $taskState): void {
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn(['state' => $taskState, 'sessions' => [['head_ref' => 'copilot/content']]]);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('pullRequestForHead')->once()->andReturn(['number' => 42]);
    $github->shouldReceive('pullRequestDetails')->once()->andReturn(contentResultDetails($this->repository));
    $notifier = $this->mock(ContentGenerationNotifier::class);
    $notifier->shouldNotReceive('ready');
    $job = new SyncContentGeneration($this->generation);
    $job->handle($copilot, $github, $notifier);
    $job->handle($copilot, $github, $notifier);
    expect($this->generation->fresh()->status)->toBe('no_work')
        ->and($this->generation->fresh()->completion_evidence['changed_files'])->toBe(0)
        ->and($this->request->fresh()->picked_up_at)->toBeNull()
        ->and($this->request->fresh()->content_generation_id)->toBeNull()
        ->and($this->request->fresh()->held_at)->not->toBeNull()
        ->and($this->request->fresh()->hold_reason)->toContain('No changes delivered')
        ->and($this->request->fresh()->instructions)->toBe('Create the original client guide');
    $replacement = $this->plan->generations()->where('opportunity_attempt', 1)->sole();
    expect($replacement->opportunity_root_id)->toBe($this->generation->id)
        ->and($replacement->scheduled_for->toDateString())->toBe($this->generation->scheduled_for->toDateString())
        ->and(app(ContentWorkSelector::class)->select($replacement)['requests']->modelKeys())->toBe([$this->next->id]);
    Queue::assertPushed(StartContentGeneration::class, 1);
    expect(app(ContentBudget::class)->usage($this->plan))->toBe(['articles' => 0, 'optimisations' => 0, 'copilot' => 1]);
})->with(['completed', 'failed', 'cancelled']);

it('retains requests when a failed task actually changed files', function (): void {
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn(['state' => 'failed', 'artifacts' => [['provider' => 'github', 'type' => 'pull', 'data' => ['number' => 42]]]]);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('pullRequestDetails')->once()->andReturn(contentResultDetails($this->repository, 1));
    $notifier = $this->mock(ContentGenerationNotifier::class);
    $notifier->shouldReceive('ready')->once();
    (new SyncContentGeneration($this->generation))->handle($copilot, $github, $notifier);
    expect($this->generation->fresh()->status)->toBe('pull_request_open')->and($this->request->fresh()->picked_up_at)->not->toBeNull();
    Queue::assertNotPushed(StartContentGeneration::class);
});

it('never treats missing or contradictory GitHub evidence as proof of no work', function (string $case): void {
    $copilot = $this->mock(CopilotAgentClient::class);
    $task = ['state' => 'completed', 'artifacts' => [['provider' => 'github', 'type' => 'pull', 'data' => ['number' => 42]]]];
    $details = contentResultDetails($this->repository);
    if ($case === 'no_artifact') {
        $task = ['state' => 'completed', 'message' => 'No files changed.'];
    } elseif ($case === 'wrong_repository') {
        $details['pull_request']['base']['repo']['id'] = -1;
    } elseif ($case === 'missing_counts') {
        unset($details['pull_request']['changed_files'], $details['pull_request']['additions']);
    } else {
        $details['pull_request']['additions'] = 1;
    }
    $copilot->shouldReceive('task')->once()->andReturn($task);
    $github = $this->mock(GithubAppClient::class);
    if ($case !== 'no_artifact') {
        $github->shouldReceive('pullRequestDetails')->once()->andReturn($details);
    }
    $notifier = $this->mock(ContentGenerationNotifier::class);
    $notifier->shouldNotReceive('ready');
    (new SyncContentGeneration($this->generation))->handle($copilot, $github, $notifier);
    expect($this->generation->fresh()->status)->toBe('failed')->and($this->request->fresh()->picked_up_at)->not->toBeNull();
    Queue::assertNotPushed(StartContentGeneration::class);
})->with(['no_artifact', 'wrong_repository', 'missing_counts', 'contradictory_counts']);

it('checks branch changes when a terminal task has no pull request', function (int $changed): void {
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn(['state' => 'completed', 'sessions' => [['head_ref' => 'copilot/content']]]);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('pullRequestForHead')->once()->andThrow(new RuntimeException('GitHub did not return a pull request for branch copilot/content.'));
    $github->shouldReceive('contentBranchChanges')->once()->andReturn(['source' => 'github_branch_compare', 'changed_files' => $changed, 'head_sha' => 'head', 'base_sha' => 'base']);
    $notifier = $this->mock(ContentGenerationNotifier::class);
    $notifier->shouldNotReceive('ready');
    (new SyncContentGeneration($this->generation))->handle($copilot, $github, $notifier);
    expect($this->generation->fresh()->status)->toBe($changed === 0 ? 'no_work' : 'failed')
        ->and($this->request->fresh()->picked_up_at === null)->toBe($changed === 0);
})->with([0, 1]);

it('bounds the same opportunity at three attempts without clearing paid usage', function (): void {
    $root = $this->generation;
    for ($attempt = 0; $attempt < 3; $attempt++) {
        $current = $this->plan->generations()->where('opportunity_attempt', $attempt)->sole();
        $current->update(['status' => 'running', 'copilot_task_state' => 'completed', 'copilot_task_id' => sprintf('%08d-1111-4111-8111-111111111111', $attempt + 1), 'budget_reserved_at' => now(), 'work_units' => ['articles' => 1, 'optimisations' => 0]]);
        if ($attempt > 0) {
            $waiting = $this->next->fresh();
            $waiting->update(['picked_up_at' => now(), 'content_generation_id' => $current->id]);
            $this->next = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Independent request number '.($attempt + 2), 'work_type' => 'new_article']);
        }
        app(ContentNoWorkRecovery::class)->recover($current, 'No changes', noWorkEvidence());
    }
    expect($this->plan->generations()->count())->toBe(3)
        ->and(app(ContentSchedule::class)->hasCapacity($this->plan, now()->toImmutable()))->toBeFalse()
        ->and(app(ContentBudget::class)->usage($this->plan)['copilot'])->toBe(3)
        ->and(app(ContentBudget::class)->usage($this->plan)['articles'])->toBe(0);
    Queue::assertPushed(StartContentGeneration::class, 2);
});

it('does not start replacements for manual expired paused or budget-exhausted work', function (string $case): void {
    if ($case === 'manual') {
        $this->generation->update(['trigger' => 'manual']);
    } elseif ($case === 'expired') {
        $this->travel(7)->days();
    } elseif ($case === 'paused') {
        $this->plan->update(['enabled' => false]);
    } else {
        $this->plan->update(['monthly_copilot_limit' => 1]);
    }
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'Blocked', noWorkEvidence());
    expect($this->generation->fresh()->status)->toBe('no_work')->and($this->request->fresh()->picked_up_at)->toBeNull();
    Queue::assertNotPushed(StartContentGeneration::class);
})->with(['manual', 'expired', 'paused', 'budget']);

it('checks file references in the brief before paying for Copilot', function (): void {
    $this->request->update(['picked_up_at' => null, 'content_generation_id' => null, 'instructions' => 'Update `public/sitemap.xml` for this guide.']);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('contentDependencyHistory')->once()->withArgs(fn ($repo, array $files): bool => $repo->is($this->repository) && $files === ['public/sitemap.xml'])
        ->andReturn(['changes' => [['file' => 'public/sitemap.xml', 'changed_at' => now()->subDay()->toIso8601String()]], 'reviews' => []]);
    $result = app(ContentWorkSelector::class)->select($this->generation, repositoryPreflight: true);
    expect($result['requests']->modelKeys())->toBe([$this->next->id])
        ->and($this->request->fresh()->preflight['state'])->toBe('cooldown')
        ->and($this->request->fresh()->picked_up_at)->toBeNull();
});

it('executes a replacement on a later day of the same week without consuming a second weekly slot', function (): void {
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    $replacement = $this->plan->generations()->where('opportunity_attempt', 1)->sole();
    $this->travel(1)->days();
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('startTask')->once()->andReturn(['id' => '22222222-2222-4222-8222-222222222222', 'state' => 'queued']);
    app()->call([new StartContentGeneration($replacement), 'handle']);
    expect($replacement->fresh()->status)->toBe('running')
        ->and($replacement->fresh()->copilot_task_id)->toBe('22222222-2222-4222-8222-222222222222')
        ->and($this->next->fresh()->content_generation_id)->toBe($replacement->id)
        ->and($this->request->fresh()->picked_up_at)->toBeNull()
        ->and(app(ContentBudget::class)->usage($this->plan)['copilot'])->toBe(2);
    app()->call([new StartContentGeneration($replacement), 'handle']);
});

it('does not replace work with no eligible next item or other active preparation', function (bool $active): void {
    if ($active) {
        ContentGeneration::factory()->for($this->plan, 'plan')->create(['scheduled_for' => now()->addDay(), 'status' => 'running']);
    } else {
        $this->next->update(['held_at' => now(), 'hold_reason' => 'Needs client approval']);
    }
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    Queue::assertNotPushed(StartContentGeneration::class);
})->with([true, false]);

it('does not return or replace a request with recorded non-GitHub work', function (): void {
    $this->request->update(['pixel_processed_at' => now()]);
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    expect($this->request->fresh()->picked_up_at)->not->toBeNull()
        ->and($this->generation->fresh()->status)->toBe('failed')
        ->and($this->generation->fresh()->error)->toContain('other work');
    Queue::assertNotPushed(StartContentGeneration::class);
});

it('rejects incomplete branch comparison responses rather than inferring zero changes', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    $github = Mockery::mock(GithubAppClient::class)->makePartial();
    $github->shouldReceive('installationToken')->once()->andReturn('test-token');
    Http::fake(['*' => Http::response(['status' => 'identical', 'ahead_by' => 0], 200)]);
    expect(fn () => $github->contentBranchChanges($this->repository, 'copilot/content'))->toThrow(RuntimeException::class, 'incomplete');
});

it('ignores late failure callbacks after verified no work', function (): void {
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    (new SyncContentGeneration($this->generation))->failed(new RuntimeException('Late worker error'));
    (new StartContentGeneration($this->generation))->failed(new RuntimeException('Late start error'));
    expect($this->generation->fresh()->status)->toBe('no_work');
});

it('blocks ambiguous multiple PRs even when the first PR might be empty', function (): void {
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn(['state' => 'completed', 'artifacts' => [
        ['provider' => 'github', 'type' => 'pull', 'data' => ['number' => 42]],
        ['provider' => 'github', 'type' => 'pull', 'data' => ['number' => 43]],
    ]]);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldNotReceive('pullRequestDetails');
    (new SyncContentGeneration($this->generation))->handle($copilot, $github, $this->mock(ContentGenerationNotifier::class));
    expect($this->generation->fresh()->status)->toBe('failed')->and($this->request->fresh()->picked_up_at)->not->toBeNull();
    Queue::assertNotPushed(StartContentGeneration::class);
});

it('release from a no-work hold clears the saved preflight blocker', function (): void {
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    $replacement = $this->plan->generations()->where('opportunity_attempt', 1)->sole();
    $replacement->update(['status' => 'skipped']);
    $this->actingAs($this->admin)->patch(route('admin.content-requests.queue.update', [$this->website, $this->request]), ['action' => 'release'])->assertRedirect();
    expect($this->request->fresh()->held_at)->toBeNull()->and($this->request->fresh()->preflight)->toBeNull();
});

it('restores only this run’s own target selection when nothing changed', function (): void {
    $keyword = SeoTargetKeyword::factory()->for($this->website)->create(['last_selected_at' => now()]);
    $previous = now()->subDays(20)->toDateTimeString();
    $this->generation->update(['seo_target_keyword_id' => $keyword->id, 'completion_evidence' => ['target_selected_at' => now()->toDateTimeString(), 'previous_target_selected_at' => $previous]]);
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    expect($keyword->fresh()->last_selected_at->toDateTimeString())->toBe($previous);
});

it('fails closed when GitHub cannot verify the result', function (): void {
    $copilot = $this->mock(CopilotAgentClient::class);
    $copilot->shouldReceive('task')->once()->andReturn(['state' => 'completed', 'artifacts' => [['provider' => 'github', 'type' => 'pull', 'data' => ['number' => 42]]]]);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('pullRequestDetails')->once()->andThrow(new RuntimeException('GitHub unavailable'));
    $job = new SyncContentGeneration($this->generation);
    expect(fn () => $job->handle($copilot, $github, $this->mock(ContentGenerationNotifier::class)))->toThrow(RuntimeException::class);
    $job->failed(new RuntimeException('GitHub unavailable'));
    expect($this->generation->fresh()->status)->toBe('failed')->and($this->request->fresh()->picked_up_at)->not->toBeNull();
    Queue::assertNotPushed(StartContentGeneration::class);
});

it('validates a real branch comparison response with an empty changed-files list', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    $github = Mockery::mock(GithubAppClient::class)->makePartial();
    $github->shouldReceive('installationToken')->once()->andReturn('test-token');
    Http::fake(['*' => Http::response(['files' => [], 'status' => 'identical', 'ahead_by' => 0, 'base_commit' => ['sha' => 'base'], 'merge_base_commit' => ['sha' => 'base']], 200)]);
    expect($github->contentBranchChanges($this->repository, 'copilot/content'))->toMatchArray(['source' => 'github_branch_compare', 'changed_files' => 0]);
});

it('keeps the original daily reservation unique while allowing bounded replacement rows', function (): void {
    expect(fn () => ContentGeneration::factory()->for($this->plan, 'plan')->create(['scheduled_for' => $this->generation->scheduled_for]))->toThrow(QueryException::class);
});

it('never repeats the same request or identical brief within the replacement chain', function (): void {
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    $replacement = $this->plan->generations()->where('opportunity_attempt', 1)->sole();
    $this->request->update(['held_at' => null, 'hold_reason' => null, 'preflight' => null]);
    ContentRequest::factory()->for($this->website)->create(['instructions' => $this->request->instructions, 'bumped_at' => now()]);
    expect(app(ContentWorkSelector::class)->select($replacement)['requests']->modelKeys())->toBe([$this->next->id]);
});

it('shows a held no-work reason and its replacement attempt in the existing content workspace', function (): void {
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'Sitemap is protected.', noWorkEvidence());
    $this->actingAs($this->admin)->get(route('admin.websites.section', [$this->website, 'content']))->assertSuccessful()->assertSee('No changes delivered. Review before releasing: Sitemap is protected.');
    $this->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'activity']))->assertSuccessful()->assertSee('attempt 2 of 3')->assertSee('Replacement attempt 2/3 queued.');
});

it('does not continue Copilot when the selected website engine has changed', function (): void {
    config(['amp_content.enabled' => true, 'amp_content.api_key' => 'test-key']);
    $this->plan->update(['content_engine' => 'amp']);
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'No change', noWorkEvidence());
    Queue::assertNotPushed(StartContentGeneration::class);
});

it('does not recover an empty intermediate result while Copilot is still running', function (): void {
    $this->generation->update(['copilot_task_state' => 'running']);
    app(ContentNoWorkRecovery::class)->recover($this->generation, 'Empty intermediate diff', noWorkEvidence());
    expect($this->generation->fresh()->status)->toBe('running')->and($this->request->fresh()->picked_up_at)->not->toBeNull();
    Queue::assertNotPushed(StartContentGeneration::class);
});
