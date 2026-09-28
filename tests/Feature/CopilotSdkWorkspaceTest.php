<?php

use App\Jobs\RunCopilotSdkTitle;
use App\Models\CopilotSdkTestRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\CopilotSdkTitleRunner;
use App\Services\GithubSdkPublisher;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    config(['copilot_sdk.enabled' => true, 'copilot_sdk.api_key' => 'test-only', 'copilot_sdk.model' => 'test-model',
        'queue.default' => 'database', 'queue.connections.database.retry_after' => 300]);
    Queue::fake();
    Http::preventStrayRequests();
    Process::preventStrayProcesses();
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->for($this->admin, 'owner')->create();
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->publisher = $this->mock(GithubSdkPublisher::class);
    $this->publisher->shouldReceive('authorize')->byDefault();
    $this->publisher->shouldReceive('snapshot')->byDefault()->andReturn([
        'base_sha' => str_repeat('a', 40), 'tree_sha' => str_repeat('b', 40), 'original' => '<title>Old</title><p>Untouched</p>',
    ]);
    $this->payload = ['path' => 'index.html', 'title' => 'New title'];
    $this->url = route('admin.sdk-runs.store', $this->website);
    $this->workspace = route('admin.websites.section', [$this->website, 'content', 'content_section' => 'sdk']);
});

function workspaceSdkRun($context, array $attributes = []): CopilotSdkTestRun
{
    return CopilotSdkTestRun::factory()->create([
        'website_repository_id' => $context->repository->id, 'requested_by' => $context->admin->id,
        'repository_id' => $context->repository->repository_id,
        'installation_id' => $context->repository->installation->installation_id,
        'full_name' => $context->repository->full_name, 'base_branch' => $context->repository->default_branch,
        'status' => 'queued', ...$attributes,
    ]);
}

it('queues a scoped snapshot without running the model in the web request', function (): void {
    $this->actingAs($this->admin)->post($this->url, $this->payload)->assertRedirect($this->workspace)->assertSessionHasNoErrors();
    $run = CopilotSdkTestRun::query()->sole();
    expect($run->status)->toBe('queued')->and($run->requested_by)->toBe($this->admin->id)
        ->and($run->original)->toBe('<title>Old</title><p>Untouched</p>');
    Queue::assertPushed(RunCopilotSdkTitle::class, fn ($job) => $job->runId === $run->id);
    Process::assertNothingRan();
});

it('blocks customers even if they manage the website', function (): void {
    $customer = User::factory()->create();
    $this->website->update(['user_id' => $customer->id]);
    $this->actingAs($customer)->post($this->url, $this->payload)->assertForbidden();
    $this->get(route('admin.sdk-runs.status', $this->website))->assertForbidden();
    Queue::assertNothingPushed();
});

it('validates the title and repository path', function (array $changes, string $field): void {
    $this->actingAs($this->admin)->from($this->workspace)->post($this->url, [...$this->payload, ...$changes])->assertSessionHasErrors($field);
    Queue::assertNothingPushed();
})->with([
    [['path' => '../index.html'], 'path'],
    [['path' => '.env'], 'path'],
    [['title' => ''], 'title'],
    [['title' => "bad\ntitle"], 'title'],
]);

it('does not charge for an unchanged title', function (): void {
    $this->actingAs($this->admin)->post($this->url, [...$this->payload, 'title' => 'Old'])->assertSessionHasNoErrors();
    expect(CopilotSdkTestRun::count())->toBe(0);
    Queue::assertNothingPushed();
});

it('blocks duplicate submissions and repository-wide concurrent work', function (): void {
    $this->actingAs($this->admin)->post($this->url, $this->payload)->assertSessionHasNoErrors();
    $this->post($this->url, $this->payload)->assertSessionHasErrors('sdk');
    expect(CopilotSdkTestRun::count())->toBe(1);
    Queue::assertPushed(RunCopilotSdkTitle::class, 1);
});

it('rejects execution when SDK or durable queue configuration is unavailable', function (string $setting, mixed $value): void {
    config([$setting => $value]);
    $this->actingAs($this->admin)->post($this->url, $this->payload)->assertSessionHasErrors('sdk');
    Queue::assertNothingPushed();
})->with([
    ['copilot_sdk.enabled', false], ['queue.default', 'sync'], ['queue.connections.database.retry_after', 90],
]);

it('executes and publishes a queued run only once', function (): void {
    $run = workspaceSdkRun($this);
    $runner = $this->mock(CopilotSdkTitleRunner::class);
    $runner->shouldReceive('run')->once()->andReturn(['replacement' => '<title>Sitewell SDK test</title>', 'usage' => ['inputTokens' => 3064, 'outputTokens' => 631]]);
    $this->publisher->shouldReceive('publish')->once()->andReturnUsing(function ($run): string {
        expect($run->status)->toBe('publishing');
        $run->update(['status' => 'pull_request_open', 'pull_request_url' => 'https://github.com/example/site/pull/1']);

        return $run->pull_request_url;
    });
    $job = new RunCopilotSdkTitle($run->id);
    $job->handle($this->publisher, $runner);
    $job->handle($this->publisher, $runner);
    expect($run->fresh()->status)->toBe('pull_request_open')->and($run->fresh()->usage['inputTokens'])->toBe(3064);
});

it('rechecks access before making a paid call', function (): void {
    $run = workspaceSdkRun($this);
    $this->publisher->shouldReceive('authorize')->once()->andThrow(new RuntimeException('private-token'));
    $runner = $this->mock(CopilotSdkTitleRunner::class);
    $runner->shouldNotReceive('run');
    (new RunCopilotSdkTitle($run->id))->handle($this->publisher, $runner);
    expect($run->fresh()->status)->toBe('failed')->and($run->fresh()->error)->not->toContain('private-token');
});

it('rejects a changed repository before making a paid call', function (): void {
    $run = workspaceSdkRun($this, ['full_name' => 'different/repo']);
    $runner = $this->mock(CopilotSdkTitleRunner::class);
    $runner->shouldNotReceive('run');
    (new RunCopilotSdkTitle($run->id))->handle($this->publisher, $runner);
    expect($run->fresh()->status)->toBe('failed');
});

it('can resume publishing without repeating a model call', function (): void {
    $run = workspaceSdkRun($this, ['status' => 'publish_failed', 'replacement' => '<title>Sitewell SDK test</title>']);
    $this->actingAs($this->admin)->post(route('admin.sdk-runs.resume', [$this->website, $run]))->assertSessionHasNoErrors();
    $this->post(route('admin.sdk-runs.resume', [$this->website, $run]))->assertSessionHasNoErrors();
    Queue::assertPushed(RunCopilotSdkTitle::class, 1);
    $runner = $this->mock(CopilotSdkTitleRunner::class);
    $runner->shouldNotReceive('run');
    $this->publisher->shouldReceive('publish')->once()->andReturnUsing(function ($run): string {
        $run->update(['status' => 'pull_request_open']);

        return 'https://github.com/example/site/pull/1';
    });
    (new RunCopilotSdkTitle($run->id))->handle($this->publisher, $runner);
    expect($run->fresh()->status)->toBe('pull_request_open');
});

it('scopes publishing retries to the current website', function (): void {
    $other = CopilotSdkTestRun::factory()->create(['requested_by' => $this->admin->id, 'status' => 'publish_failed']);
    $this->actingAs($this->admin)->post(route('admin.sdk-runs.resume', [$this->website, $other]))->assertNotFound();
    Queue::assertNothingPushed();
});

it('records safe failures and preserves validated output for publishing retries', function (): void {
    $run = workspaceSdkRun($this);
    $runner = $this->mock(CopilotSdkTitleRunner::class);
    $runner->shouldReceive('run')->once()->andReturn(['replacement' => '<title>Sitewell SDK test</title>', 'usage' => []]);
    $this->publisher->shouldReceive('publish')->once()->andThrow(new RuntimeException('private-token'));
    (new RunCopilotSdkTitle($run->id))->handle($this->publisher, $runner);
    expect($run->fresh()->status)->toBe('publish_failed')->and($run->fresh()->error)->not->toContain('private-token');
});

it('marks timed-out work failed without retrying paid execution', function (): void {
    $run = workspaceSdkRun($this, ['status' => 'running']);
    $job = new RunCopilotSdkTitle($run->id);
    $job->failed(new RuntimeException('secret'));
    expect($job->tries)->toBe(1)->and($job->failOnTimeout)->toBeTrue()->and($run->fresh()->status)->toBe('failed');
});

it('renders scoped progress and token usage without repository contents', function (): void {
    $run = workspaceSdkRun($this, ['status' => 'pull_request_open', 'usage' => ['inputTokens' => 3064, 'outputTokens' => 631],
        'pull_request_url' => 'https://github.com/example/site/pull/1', 'original' => 'PRIVATE_HTML']);
    $other = CopilotSdkTestRun::factory()->create();
    $this->actingAs($this->admin)->get(route('admin.sdk-runs.status', $this->website))->assertOk()
        ->assertSee('Ready for review')->assertSee('3,064 input / 631 output')->assertSee($run->run_id)
        ->assertDontSee($other->run_id)->assertDontSee('PRIVATE_HTML');
    $this->get($this->workspace)->assertOk()->assertSee('Run with SDK')->assertSee('SDK test');
});

it('blocks a repeated change that already has an open PR', function (): void {
    workspaceSdkRun($this, ['status' => 'pull_request_open', 'title' => 'New title']);
    $this->actingAs($this->admin)->post($this->url, $this->payload)->assertSessionHasErrors('sdk');
    Queue::assertNothingPushed();
});

it('honours disabling the SDK after a run was queued', function (): void {
    $run = workspaceSdkRun($this);
    config(['copilot_sdk.enabled' => false]);
    $runner = $this->mock(CopilotSdkTitleRunner::class);
    $runner->shouldNotReceive('run');
    $this->publisher->shouldNotReceive('publish');
    (new RunCopilotSdkTitle($run->id))->handle($this->publisher, $runner);
    expect($run->fresh()->status)->toBe('failed');
});

it('keeps customer content pages free of SDK controls and run data', function (): void {
    $customer = User::factory()->create();
    $this->website->update(['user_id' => $customer->id]);
    $run = workspaceSdkRun($this);
    $this->actingAs($customer)->get($this->workspace)->assertOk()->assertDontSee('Run with SDK')->assertDontSee($run->run_id);
});

it('the command respects work already queued from the workspace', function (): void {
    workspaceSdkRun($this);
    $this->artisan('copilot-sdk:test-repository', ['website' => $this->website->id, '--user' => $this->admin->id, '--publish' => true])
        ->expectsOutput('Another SDK run is already active for this repository.')->assertFailed();
    Process::assertNothingRan();
});
