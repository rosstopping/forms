<?php

use App\Models\AgentContentTask;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteHealthReport;
use App\Models\WebsiteHealthReportPage;
use App\Models\WebsiteLocalProject;
use App\Services\ManualContentRequest;
use App\Services\SeoImpactTracker;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 10, 23)->setTime(8, 0));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'admin_site_access' => 'assigned']);
    $this->website = Website::factory()->create();
    $this->website->domains()->create(['domain' => 'rowglo.example', 'ownership_status' => 'verified', 'is_primary' => true]);
    $this->admin->assignedWebsites()->attach($this->website);
    $this->plan = ContentPlan::factory()->for($this->website)->create(['content_engine' => 'manual', 'enabled' => false, 'weekday' => 5]);
    $this->project = WebsiteLocalProject::factory()->for($this->website)->create(['path' => '/Users/example/Code/rowglo']);
    $this->report = WebsiteHealthReport::factory()->for($this->website)->create(['status' => 'completed', 'completed_at' => now()->subDay()]);
    $this->page = WebsiteHealthReportPage::factory()->for($this->report, 'report')->create(['url' => 'https://rowglo.example/boilers/']);
    $this->proposal = ['scheduled_for' => '2026-10-23', 'instructions' => 'Improve the boiler installation page using verified service facts and helpful internal links.',
        'work_type' => 'optimisation', 'rationale' => 'The saved crawl shows a useful opportunity on the existing boiler service page; coverage and relevance were reviewed.',
        'host' => 'local', 'path' => $this->project->path, 'repository' => 'example/rowglo', 'coverage_reviewed' => true,
        'repository_reviewed' => true, 'open_prs_reviewed' => true, 'target_urls' => ['https://rowglo.example/boilers/'],
        'target_queries' => ['boiler installation doncaster'], 'target_files' => ['boilers/index.html'],
        'evidence' => [['section' => 'health-pages', 'id' => $this->page->id]]];
    $this->token = str_repeat('agent-work-token-', 3);
    config(['services.sitewell_agent.token_hash' => hash('sha256', $this->token), 'services.sitewell_agent.user_id' => $this->admin->id]);
    $this->withToken($this->token);
});

test('due empty websites receive one evidenced reserved manual task without starting hosted work', function (): void {
    $response = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()
        ->assertJsonPath('read_only', false)->assertJsonPath('task.status', 'in_progress')
        ->assertJsonPath('task.execution.merge_allowed', false)->assertJsonPath('task.execution.publish_allowed', false);
    $task = AgentContentTask::query()->sole();
    $request = $task->contentRequest;
    expect($request->manual_taken_by)->toBe($this->admin->id)->and($request->manual_started_at)->not->toBeNull()
        ->and($request->budget_reserved_at)->not->toBeNull()->and($request->manual_completed_at)->toBeNull()
        ->and($request->seoImpact->target_urls)->toBe($this->proposal['target_urls'])
        ->and($task->evidence[0]['record']['url'])->toBe('https://rowglo.example/boilers/')
        ->and($response->json('task.prompt'))->toContain('draft pull request', 'do not publish or merge automatically');
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('repeated identical proposals return the reservation while changed proposals cannot create another task', function (): void {
    $first = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->json('task.id');
    $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->assertJsonPath('task.id', $first);
    $this->postJson(route('agent.tasks.store', $this->website), [...$this->proposal, 'instructions' => 'Prepare a completely different service page improvement instead.'])->assertConflict();
    expect(AgentContentTask::count())->toBe(1)->and(ContentRequest::count())->toBe(1);
});

test('automatic proposals respect queue activity schedule holds budgets and recent changes', function (string $blocker): void {
    match ($blocker) {
        'queue' => ContentRequest::factory()->for($this->website)->create(),
        'held queue' => ContentRequest::factory()->for($this->website)->create(['held_at' => now()]),
        'active manual' => ContentRequest::factory()->for($this->website)->create(['picked_up_at' => now(), 'manual_started_at' => now()]),
        'completed today' => ContentRequest::factory()->for($this->website)->create(['picked_up_at' => now(), 'manual_started_at' => now(), 'manual_completed_at' => now()]),
        'recent change' => ContentRequest::factory()->for($this->website)->create(['instructions' => 'Recently improved https://rowglo.example/boilers/', 'picked_up_at' => now()->subDay(), 'manual_started_at' => now()->subDay(), 'manual_completed_at' => now()->subDay()]),
        'not due' => $this->plan->update(['weekday' => 6]),
        'skipped' => $this->plan->update(['manual_skipped_on' => today()->toDateString()]),
        'automatic engine' => $this->plan->update(['content_engine' => 'copilot']),
        'reminders off' => $this->plan->update(['manual_reminders_enabled' => false]),
        'budget' => $this->plan->update(['monthly_optimisation_limit' => 0]),
        'wrong strategy' => $this->plan->update(['content_mode' => 'new_only']),
    };
    $before = ContentRequest::count();
    $response = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal);
    expect($response->status())->toBeIn([409, 422])->and(AgentContentTask::count())->toBe(0)->and(ContentRequest::count())->toBe($before);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
})->with(['queue', 'held queue', 'active manual', 'completed today', 'recent change', 'not due', 'skipped', 'automatic engine', 'reminders off', 'budget', 'wrong strategy']);

test('proposals require site owned recent evidence declared scope local project and review attestations', function (array $change): void {
    $this->postJson(route('agent.tasks.store', $this->website), [...$this->proposal, ...$change])->assertUnprocessable();
    expect(AgentContentTask::count())->toBe(0)->and(ContentRequest::count())->toBe(0);
})->with([
    [['coverage_reviewed' => false]], [['repository_reviewed' => false]], [['open_prs_reviewed' => false]],
    [['evidence' => []]], [['evidence' => [['section' => 'health-pages', 'id' => 999999]]]],
    [['target_urls' => ['https://other.example/service/']]], [['path' => '/different/checkout']],
    [['target_files' => ['../secrets']]], [['target_files' => ['.env']]],
]);

test('old or foreign evidence cannot be cited to justify a new task', function (bool $foreign): void {
    if ($foreign) {
        $this->report->update(['website_id' => Website::factory()->create()->id]);
    } else {
        $this->report->update(['completed_at' => now()->subDays(91)]);
    }
    $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertUnprocessable();
    expect(AgentContentTask::count())->toBe(0)->and(ContentRequest::count())->toBe(0);
})->with([true, false]);

test('task writes and prompts require the current assigned admin and correct website', function (): void {
    $id = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->json('task.id');
    $foreign = Website::factory()->create();
    $this->admin->assignedWebsites()->attach($foreign);
    $this->getJson(route('agent.tasks.show', [$foreign, $id]))->assertNotFound();
    $this->admin->assignedWebsites()->detach($this->website);
    $this->getJson(route('agent.tasks.show', [$this->website, $id]))->assertNotFound();
    $this->postJson(route('agent.tasks.blocked', [$this->website, $id]), ['reason' => 'A project problem prevents safe progress.'])->assertNotFound();
    $this->withHeaders(['Authorization' => ''])->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertUnauthorized();
});

test('draft PR reporting is idempotent preserves the reservation and never records publication', function (): void {
    $task = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->json('task');
    $result = ['pull_request_url' => 'https://github.com/example/rowglo/pull/12', 'is_draft' => true, 'state' => 'open', 'branch' => $task['branch'],
        'head_sha' => str_repeat('a', 40), 'changed_files' => 2, 'summary' => 'Improved the service page and checked its links.', 'checks' => ['Production build passed']];
    foreach ([1, 2] as $attempt) {
        $this->postJson(route('agent.tasks.draft-pr', [$this->website, $task['id']]), $result)->assertSuccessful()->assertJsonPath('task.status', 'draft_pr_reported');
    }
    $saved = AgentContentTask::query()->sole();
    expect($saved->contentRequest->manual_completed_at)->toBeNull()->and($saved->contentRequest->picked_up_at)->not->toBeNull()
        ->and($saved->contentRequest->seoImpact->live_at)->toBeNull()->and($saved->result['verification_source'])->toBe('agent_github_cli');
    $this->getJson(route('agent.work'))->assertSuccessful()->assertJsonPath('agent_tasks.0.pull_request_url', $result['pull_request_url']);
    $this->actingAs($this->admin)->get(route('admin.content-queue.index', ['task' => $task['request_id']]))->assertSuccessful()->assertSee('Review draft PR')->assertSee($result['pull_request_url']);
    Queue::assertNothingPushed();
    Http::assertNothingSent();
});

test('wrong repository branch non draft empty change and merged PR reports are rejected', function (array $change): void {
    $task = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->json('task');
    $result = ['pull_request_url' => 'https://github.com/example/rowglo/pull/12', 'is_draft' => true, 'state' => 'open', 'branch' => $task['branch'],
        'head_sha' => str_repeat('a', 40), 'changed_files' => 2, 'summary' => 'Improved the service page and checked its links.', 'checks' => ['Production build passed']];
    $this->postJson(route('agent.tasks.draft-pr', [$this->website, $task['id']]), [...$result, ...$change])->assertUnprocessable();
    expect(AgentContentTask::query()->sole()->pull_request_url)->toBeNull();
})->with([
    [['is_draft' => false]], [['state' => 'closed']], [['changed_files' => 0]], [['branch' => 'main']],
    [['pull_request_url' => 'https://github.com/other/site/pull/12']], [['checks' => []]],
]);

test('blocked tasks retain their reservation and cannot be replaced or silently marked done', function (): void {
    $id = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->json('task.id');
    $this->postJson(route('agent.tasks.blocked', [$this->website, $id]), ['reason' => 'Repository build fails before any safe change can be prepared.'])
        ->assertSuccessful()->assertJsonPath('task.status', 'blocked');
    $saved = AgentContentTask::query()->sole();
    expect($saved->contentRequest->held_at)->not->toBeNull()->and($saved->contentRequest->manual_completed_at)->toBeNull();
    $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->assertJsonPath('task.status', 'blocked');
    expect(ContentRequest::count())->toBe(1);
});

test('returned reservations reject draft reports and another admin cannot read their prompt', function (): void {
    $task = $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful()->json('task');
    $saved = AgentContentTask::query()->sole();
    app(ManualContentRequest::class)->returnToQueue($saved->contentRequest);
    $this->postJson(route('agent.tasks.blocked', [$this->website, $task['id']]), ['reason' => 'The task reservation has been returned.'])->assertConflict();
    $other = User::factory()->create(['role' => User::ROLE_ADMIN]);
    config(['services.sitewell_agent.user_id' => $other->id]);
    $this->getJson(route('agent.tasks.show', [$this->website, $task['id']]))->assertNotFound();
});

test('draft reporting command verifies the local root origin branch commit and live GitHub draft', function (bool $draft): void {
    $directory = sys_get_temp_dir().'/sitewell-agent-'.Str::uuid();
    mkdir($directory);
    $checks = $directory.'/checks.json';
    file_put_contents($checks, json_encode(['Production build passed']));
    $branch = 'codex/sitewell-1-2026-10-23-1';
    $sha = str_repeat('a', 40);
    $task = ['id' => 1, 'status' => 'in_progress', 'reservation_active' => true, 'project' => ['host' => 'local', 'path' => $directory], 'repository' => 'example/rowglo', 'branch' => $branch];
    config(['services.sitewell_agent.endpoint' => 'https://sitewell.example/api/agent/v1/work', 'services.sitewell_agent.token' => $this->token]);
    Http::fake(fn ($request) => Http::response(['read_only' => $request->method() === 'GET', 'task' => $task]));
    Process::preventStrayProcesses();
    Process::fake(function ($process) use ($directory, $branch, $sha, $draft) {
        $command = $process->command;
        $output = match ($command) {
            ['git', 'rev-parse', '--show-toplevel'] => $directory,
            ['git', 'remote', 'get-url', 'origin'] => 'git@github.com:example/rowglo.git',
            ['git', 'branch', '--show-current'] => $branch,
            ['git', 'rev-parse', 'HEAD'] => $sha,
            ['git', 'status', '--porcelain'] => '',
            ['gh', 'api', 'repos/example/rowglo/pulls/12'] => json_encode(['draft' => $draft, 'state' => 'open', 'merged_at' => null,
                'head' => ['ref' => $branch, 'sha' => $sha, 'repo' => ['full_name' => 'example/rowglo']], 'base' => ['repo' => ['full_name' => 'example/rowglo']], 'changed_files' => 2]),
            default => throw new RuntimeException('Unexpected process'),
        };

        return Process::result(output: $output);
    });
    try {
        $exit = Artisan::call('agent:task', ['action' => 'report', 'website' => '1', 'task' => '1', '--pr' => 'https://github.com/example/rowglo/pull/12', '--summary' => 'Improved useful service content and checked internal links.', '--checks' => $checks]);
        expect($exit)->toBe($draft ? 0 : 1, Artisan::output());
        if ($draft) {
            Http::assertSent(fn ($request): bool => $request->method() === 'POST' && str_ends_with($request->url(), '/tasks/1/draft-pr')
                && $request['head_sha'] === $sha && $request['is_draft'] === true);
        } else {
            Http::assertSentCount(1);
        }
    } finally {
        unlink($checks);
        rmdir($directory);
    }
})->with([true, false]);

test('proposal command refuses mismatched local repositories without mutating the API', function (): void {
    $directory = sys_get_temp_dir().'/sitewell-agent-'.Str::uuid();
    mkdir($directory);
    $file = $directory.'/proposal.json';
    file_put_contents($file, json_encode([...$this->proposal, 'path' => $directory]));
    Process::preventStrayProcesses();
    Process::fake(fn ($process) => Process::result(output: $process->command[1] === 'rev-parse' ? $directory : 'git@github.com:other/site.git'));
    try {
        $this->artisan('agent:task', ['action' => 'create', 'website' => '1', '--proposal' => $file])->assertFailed();
        Http::assertNothingSent();
    } finally {
        unlink($file);
        rmdir($directory);
    }
});

test('reading evidence does not exhaust the separately throttled queue mutation allowance', function (): void {
    for ($i = 0; $i < 12; $i++) {
        $this->getJson(route('agent.websites.evidence', [$this->website, 'targets']))->assertSuccessful();
    }
    $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertSuccessful();
});

test('planned content covering the same target prevents duplicate automatic work', function (): void {
    $planned = ContentRequest::factory()->for($this->website)->create(['planning_status' => 'planned', 'instructions' => 'An existing approved business brief awaiting queue selection.']);
    app(SeoImpactTracker::class)->forRequest($planned)->update(['target_urls' => $this->proposal['target_urls']]);
    $this->postJson(route('agent.tasks.store', $this->website), $this->proposal)->assertConflict();
    expect(AgentContentTask::count())->toBe(0)->and(ContentRequest::count())->toBe(1);
});
