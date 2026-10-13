<?php

use App\Jobs\RunAmpContentGeneration;
use App\Jobs\StartContentGeneration;
use App\Jobs\SyncContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\ContentSchedule;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

uses(RefreshDatabase::class);

beforeEach(function (): void {
    config([
        'amp_content.enabled' => true,
        'amp_content.api_key' => 'sgamp_test_key',
        'services.github.api_url' => 'https://api.github.test',
    ]);
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->create(['name' => 'Digizu']);
    $this->repository = WebsiteRepository::factory()->for($this->website)->create(['full_name' => 'rosstopping/digizu', 'repository_id' => 4242]);
    $this->plan = ContentPlan::factory()->create(['website_id' => $this->website->id, 'created_by' => $this->admin->id, 'content_engine' => ContentPlan::ENGINE_AMP]);
});

function ampGeneration(array $attributes = []): ContentGeneration
{
    return ContentGeneration::factory()->create([
        'content_plan_id' => test()->plan->id,
        'website_repository_id' => test()->repository->id,
        'requested_by' => test()->admin->id,
        ...$attributes,
    ]);
}

function runningAmpGeneration(array $attributes = []): ContentGeneration
{
    $generation = ampGeneration(['status' => ContentGeneration::STATUS_RUNNING, 'engine' => ContentPlan::ENGINE_AMP, 'started_at' => now(), 'prompt' => 'Improve the Tenerife guide.', ...$attributes]);
    $generation->update(['head_branch' => $attributes['head_branch'] ?? 'sitewell/content-'.$generation->id]);

    return $generation;
}

/**
 * Fake GitHub so the run's pull request appears only once Amp has opened it.
 *
 * @param  array<string, mixed>  $pullRequest
 */
function fakeGithubPullRequests(array $pullRequest = []): void
{
    test()->partialMock(GithubAppClient::class)->shouldAllowMockingProtectedMethods()->shouldReceive('jwt')->andReturn('app-jwt');
    test()->ampOpenedPullRequest = false;
    Http::preventStrayRequests();
    Http::fake(function (Request $request) use ($pullRequest) {
        if (str_ends_with($request->url(), '/access_tokens')) {
            return Http::response(['token' => 'ghs_installation_token']);
        }
        if ($request->method() === 'GET' && str_contains($request->url(), '/repos/rosstopping/digizu/pulls?')) {
            return Http::response(test()->ampOpenedPullRequest ? [[
                'number' => 17,
                'html_url' => 'https://github.com/rosstopping/digizu/pull/17',
                'state' => 'open',
                'base' => ['ref' => 'main', 'repo' => ['id' => 4242]],
                'head' => ['ref' => 'sitewell/content', 'repo' => ['id' => 4242]],
                ...$pullRequest,
            ]] : []);
        }

        throw new RuntimeException('Unexpected HTTP request: '.$request->method().' '.$request->url());
    });
}

/**
 * Fake the Node worker. The callback receives the decoded request and returns its result event.
 */
function fakeAmpWorker(Closure $result): void
{
    Process::preventStrayProcesses();
    Process::fake(['*amp-worker*' => function (PendingProcess $process) use ($result) {
        $request = json_decode($process->input, true);
        $event = $result($request, $process);

        return Process::result(output: json_encode(['event' => 'session', 'sessionId' => 'T-0199aa11-2222-7333-8444-555566667777'])."\n"
            .json_encode(['event' => 'result', 'protocolVersion' => 1, 'runId' => $request['runId'], 'sessionId' => 'T-0199aa11-2222-7333-8444-555566667777', ...$event])."\n");
    }]);
}

test('an Amp website runs through normal selection, never calls Copilot and queues one Amp run on its own queue', function (): void {
    Queue::fake();
    $request = ContentRequest::factory()->for($this->website)->create(['created_by' => $this->admin->id, 'instructions' => 'Write a guide to villas in Tenerife.']);
    $generation = ampGeneration();
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');

    app()->call([new StartContentGeneration($generation), 'handle']);

    $generation->refresh();
    expect($generation->status)->toBe(ContentGeneration::STATUS_RUNNING)
        ->and($generation->engine)->toBe(ContentPlan::ENGINE_AMP)
        ->and($generation->head_branch)->toBe('sitewell/content-'.$generation->id)
        ->and($generation->copilot_task_id)->toBeNull()
        ->and($generation->prompt)->toContain('Write a guide to villas in Tenerife.')
        ->and($generation->prompt)->toContain('belongs only to the GitHub repository rosstopping/digizu')
        ->and($generation->prompt)->toContain('gh pr create --repo rosstopping/digizu --base main --head sitewell/content-'.$generation->id.' --draft')
        ->and($request->fresh()->content_generation_id)->toBe($generation->id)
        ->and($request->fresh()->picked_up_at)->not->toBeNull();
    Queue::assertPushed(RunAmpContentGeneration::class, fn (RunAmpContentGeneration $job): bool => $job->generation->is($generation)
        && $job->connection === 'amp-content' && $job->queue === 'amp-content');
    Queue::assertNotPushed(SyncContentGeneration::class);
});

test('Amp websites are skipped visibly instead of falling back to Copilot when Amp is unavailable', function (): void {
    Queue::fake();
    config(['amp_content.enabled' => false]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $request = ContentRequest::factory()->for($this->website)->create(['created_by' => $this->admin->id]);
    $generation = ampGeneration();
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');

    app()->call([new StartContentGeneration($generation), 'handle']);

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED)
        ->and($generation->fresh()->skip_reason)->toContain('Copilot is not used as a fallback')
        ->and($request->fresh()->picked_up_at)->toBeNull()
        ->and(app(ContentSchedule::class)->pauseReason($this->plan->fresh()->forceFill(['enabled' => true])))->toBe('Amp is selected for this website but is not enabled on this server.');
    Queue::assertNothingPushed();
});

test('scheduled Amp content needs no Copilot user authorization and copilot sites still do', function (): void {
    $this->travelTo(now('Europe/London')->startOfHour());
    Queue::fake();
    $this->plan->update(['enabled' => true, 'weekday' => now('Europe/London')->dayOfWeek, 'hour' => now('Europe/London')->hour]);

    $this->artisan('content:dispatch')->assertSuccessful();

    expect($this->plan->generations()->count())->toBe(1);
    Queue::assertPushed(StartContentGeneration::class, 1);

    $this->plan->update(['content_engine' => ContentPlan::ENGINE_COPILOT]);
    expect(app(ContentSchedule::class)->pauseReason($this->plan->fresh()))->toBe('Ask the Sitewell team to connect content automation.');
});

test('the Amp run records the thread and marks the pull request Amp opened in the site repository ready for review', function (): void {
    Mail::fake();
    putenv('GITHUB_APP_PRIVATE_KEY=app-private-key-that-must-not-leak');
    fakeGithubPullRequests();
    $generation = runningAmpGeneration();
    fakeAmpWorker(function (array $request, PendingProcess $process) use ($generation): array {
        expect($request['project'])->toBe('rosstopping/digizu')
            ->and($request['prompt'])->toBe('Improve the Tenerife guide.')
            ->and($process->environment['AMP_API_KEY'])->toBe('sgamp_test_key')
            ->and($process->environment['GITHUB_APP_PRIVATE_KEY'])->toBeFalse()
            ->and($generation->fresh()->pull_request_number)->toBeNull();
        test()->ampOpenedPullRequest = true;

        return ['status' => 'completed', 'result' => 'https://github.com/rosstopping/digizu/pull/17', 'usage' => ['inputTokens' => 1200, 'outputTokens' => 300]];
    });

    app()->call([new RunAmpContentGeneration($generation), 'handle']);
    putenv('GITHUB_APP_PRIVATE_KEY');

    $generation->refresh();
    expect($generation->status)->toBe(ContentGeneration::STATUS_PULL_REQUEST_OPEN)
        ->and($generation->pull_request_number)->toBe(17)
        ->and($generation->pull_request_url)->toBe('https://github.com/rosstopping/digizu/pull/17')
        ->and($generation->agent_thread_id)->toBe('T-0199aa11-2222-7333-8444-555566667777')
        ->and($generation->agentThreadUrl())->toBe('https://ampcode.com/threads/T-0199aa11-2222-7333-8444-555566667777')
        ->and($generation->agent_usage)->toMatchArray(['inputTokens' => 1200, 'outputTokens' => 300])
        ->and($generation->notification_emailed_at)->not->toBeNull();
    Process::assertRanTimes(fn (PendingProcess $process): bool => str_contains(implode(' ', (array) $process->command), 'amp-worker'), 1);
    Http::assertSent(fn (Request $request): bool => str_contains($request->url(), 'head=rosstopping%3Asitewell%2Fcontent-'.$generation->id));
});

test('a pull request that is not against the site repository default branch is never marked ready', function (array $pullRequest): void {
    fakeGithubPullRequests($pullRequest);
    $generation = runningAmpGeneration();
    fakeAmpWorker(function (): array {
        test()->ampOpenedPullRequest = true;

        return ['status' => 'completed', 'result' => 'Opened a pull request.'];
    });

    app()->call([new RunAmpContentGeneration($generation), 'handle']);

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_FAILED)
        ->and($generation->fresh()->pull_request_number)->toBeNull()
        ->and($generation->fresh()->error)->toContain('is not in rosstopping/digizu against main');
})->with([
    'other base branch' => [['base' => ['ref' => 'production', 'repo' => ['id' => 4242]]]],
    'fork head' => [['head' => ['ref' => 'sitewell/content', 'repo' => ['id' => 9999]]]],
]);

test('an Amp failure without a pull request fails the run with Amp\'s reason', function (): void {
    fakeGithubPullRequests();
    $generation = runningAmpGeneration();
    fakeAmpWorker(fn (): array => ['status' => 'failed', 'error' => 'agent_error', 'message' => 'No accessible Amp project matches "rosstopping/digizu"']);

    app()->call([new RunAmpContentGeneration($generation), 'handle']);

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_FAILED)
        ->and($generation->fresh()->error)->toContain('No accessible Amp project matches')
        ->and($generation->fresh()->agent_thread_id)->toBe('T-0199aa11-2222-7333-8444-555566667777');
});

test('a run whose Amp thread already started only rechecks GitHub and never starts a second Amp run', function (bool $pullRequestExists): void {
    fakeGithubPullRequests();
    test()->ampOpenedPullRequest = $pullRequestExists;
    $generation = runningAmpGeneration(['agent_thread_id' => 'T-0199aa11-2222-7333-8444-555566667777']);
    Process::preventStrayProcesses();
    Process::fake();

    app()->call([new RunAmpContentGeneration($generation), 'handle']);

    Process::assertNothingRan();
    expect($generation->fresh()->status)->toBe($pullRequestExists ? ContentGeneration::STATUS_PULL_REQUEST_OPEN : ContentGeneration::STATUS_FAILED);
})->with(['pull request found' => true, 'no pull request yet' => false]);

test('retrying a failed Amp run queues the Amp job without Copilot authorization', function (): void {
    Queue::fake();
    $generation = runningAmpGeneration(['status' => ContentGeneration::STATUS_FAILED, 'error' => 'The Amp worker could not be started.']);

    $this->actingAs($this->admin)
        ->post(route('admin.content-generations.retry', [$this->website, $generation]))
        ->assertSessionHas('status');

    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_RUNNING);
    Queue::assertPushed(RunAmpContentGeneration::class, 1);
    Queue::assertNotPushed(StartContentGeneration::class);
});

test('admins switch a site between Copilot and Amp and cannot select Amp when it is unavailable', function (): void {
    $this->plan->update(['content_engine' => ContentPlan::ENGINE_COPILOT]);
    $settings = ['enabled' => false, 'weekday' => 1, 'hour' => 8, 'timezone' => 'Europe/London', 'content_engine' => ContentPlan::ENGINE_AMP];

    $this->actingAs($this->admin)->put(route('admin.content-plans.update', $this->website), $settings)->assertSessionDoesntHaveErrors();
    expect($this->plan->fresh()->content_engine)->toBe(ContentPlan::ENGINE_AMP);

    $this->actingAs($this->admin)->get(route('admin.websites.section', [$this->website, 'content', 'content_section' => 'automation']))
        ->assertSuccessful()->assertSee('Content engine')->assertSee('<option value="amp" selected', false);

    config(['amp_content.enabled' => false]);
    $this->plan->refresh()->update(['content_engine' => ContentPlan::ENGINE_COPILOT]);
    $this->actingAs($this->admin)->put(route('admin.content-plans.update', $this->website), $settings)->assertSessionHasErrors('content_engine');
    expect($this->plan->fresh()->content_engine)->toBe(ContentPlan::ENGINE_COPILOT);
});
