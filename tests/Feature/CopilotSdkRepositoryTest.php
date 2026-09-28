<?php

use App\Models\CopilotSdkTestRun;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\CopilotSdkTitleRunner;
use App\Services\GithubCustomerRepositories;
use App\Services\GithubSdkPublisher;
use Illuminate\Http\Client\Request;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

beforeEach(function (): void {
    config(['services.github.api_url' => 'https://api.github.test', 'copilot_sdk.enabled' => true,
        'copilot_sdk.api_key' => 'model-secret', 'copilot_sdk.model' => 'test-model']);
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->for($this->admin, 'owner')->create();
    $this->repository = WebsiteRepository::factory()->for($this->website)->create(['full_name' => 'sitewellross/test']);
    $this->original = '<html><head><title>Home</title></head><body>Untouched</body></html>';
    $this->blobSha = sha1('blob '.strlen($this->original)."\0".$this->original);
    $this->baseSha = str_repeat('a', 40);
    $this->treeSha = str_repeat('b', 40);
    $this->commitSha = str_repeat('c', 40);
    $this->branchExists = false;
    $this->prExists = false;
    $this->prState = 'open';
    $this->losePrResponse = false;
    $this->revoked = false;
    $this->permissionsChecked = 0;
    $this->treeMode = '100644';
    $this->truncatedTree = false;
    $this->modelOutputInvalid = false;
    $this->baseMoved = false;
    $this->commands = ['website' => $this->website->id, '--user' => $this->admin->id];
    $this->mock(GithubCustomerRepositories::class)->shouldReceive('selected')->andReturnUsing(function () {
        $this->permissionsChecked++;
        if ($this->revoked && $this->permissionsChecked > 1) {
            abort(403);
        }

        return ['id' => $this->repository->repository_id, 'full_name' => 'sitewellross/test'];
    });
    $this->partialMock(GithubSdkPublisher::class)->shouldAllowMockingProtectedMethods()->shouldReceive('jwt')->andReturn('app-jwt');
    Http::preventStrayRequests();
    Http::fake(function (Request $request) {
        $url = $request->url();
        if (str_ends_with($url, '/access_tokens')) {
            return Http::response(['token' => 'scoped-token']);
        }
        if (str_contains($url, '/commits/main')) {
            return Http::response(['sha' => $this->baseMoved && $this->permissionsChecked > 1 ? str_repeat('d', 40) : $this->baseSha, 'commit' => ['tree' => ['sha' => $this->treeSha]]]);
        }
        if (str_contains($url, '/git/trees/') && $request->method() === 'GET') {
            return Http::response(['truncated' => $this->truncatedTree, 'tree' => [['path' => 'index.html', 'type' => 'blob', 'mode' => $this->treeMode, 'size' => strlen($this->original), 'sha' => $this->blobSha]]]);
        }
        if (str_contains($url, '/git/blobs/')) {
            return Http::response(['encoding' => 'base64', 'content' => base64_encode($this->original)]);
        }
        if (str_contains($url, '/git/ref/heads/')) {
            return $this->branchExists ? Http::response(['object' => ['sha' => $this->commitSha]]) : Http::response([], 404);
        }
        if (str_ends_with($url, '/git/trees')) {
            return Http::response(['sha' => str_repeat('e', 40)], 201);
        }
        if (str_ends_with($url, '/git/commits')) {
            return Http::response(['sha' => $this->commitSha], 201);
        }
        if (str_ends_with($url, '/git/refs')) {
            $this->branchExists = true;

            return Http::response([], 201);
        }
        if (str_contains($url, '/pulls')) {
            $pull = ['number' => 7, 'state' => $this->prState, 'head' => ['sha' => $this->commitSha], 'base' => ['ref' => 'main']];
            if ($request->method() === 'GET') {
                return Http::response($this->prExists ? [$pull] : []);
            }
            $this->prExists = true;
            if ($this->losePrResponse) {
                return Http::response(['message' => 'sensitive response'], 503);
            }

            return Http::response($pull, 201);
        }
        throw new RuntimeException('Unexpected HTTP request: '.$url);
    });
    Process::preventStrayProcesses();
    Process::fake(function (PendingProcess $process) {
        $request = json_decode($process->input, true);
        $after = str_replace('<title>Home</title>', '<title>Sitewell SDK test</title>', $this->original);

        return Process::result(output: json_encode([
            'protocolVersion' => 1, 'runId' => $request['runId'], 'fixture' => 'repository-title', 'mode' => 'live', 'status' => 'validated',
            'verification' => [['passed' => true]],
            'changes' => [['path' => 'index.html', 'before' => $this->original, 'after' => $this->modelOutputInvalid ? $after.'<script>bad()</script>' : $after]],
            'usage' => ['inputTokens' => 100, 'outputTokens' => 50],
        ]));
    });
});

it('checks readiness without paying for a model or writing to a repository', function (): void {
    $this->artisan('copilot-sdk:test-repository', $this->commands)->assertSuccessful();
    Process::assertNothingRan();
    expect(CopilotSdkTestRun::count())->toBe(0);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/repos/'));
});

it('runs the SDK and publishes exactly one independently verified change to a draft PR', function (): void {
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertSuccessful();
    $run = CopilotSdkTestRun::sole();
    expect($run->status)->toBe('pull_request_open')->and($run->pull_request_url)->toBe('https://github.com/sitewellross/test/pull/7')
        ->and($run->usage)->toMatchArray(['inputTokens' => 100, 'outputTokens' => 50])
        ->and($this->permissionsChecked)->toBe(2)
        ->and($run->getRawOriginal('original'))->not->toContain('<html>');
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/access_tokens')
        && $request['repository_ids'] === [$this->repository->repository_id]
        && $request['permissions'] === ['contents' => 'write', 'pull_requests' => 'write']);
    Http::assertSent(fn (Request $request) => str_ends_with($request->url(), '/git/trees')
        && $request['base_tree'] === $this->treeSha && count($request['tree']) === 1
        && $request['tree'][0]['content'] === $run->replacement);
    Http::assertSent(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/pulls') && $request['draft'] === true);
    Process::assertRan(fn (PendingProcess $process) => ! str_contains($process->input, 'secret') && ! str_contains($process->input, 'scoped-token'));
    Http::assertNotSent(fn (Request $request) => in_array($request->method(), ['PUT', 'PATCH']));
});

it('rejects model changes outside the approved title before writing anything', function (): void {
    $this->modelOutputInvalid = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    expect(CopilotSdkTestRun::sole()->status)->toBe('failed');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/repos/'));
});

it('rechecks access and the base commit before publication', function (string $condition): void {
    $this->{$condition} = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    expect(CopilotSdkTestRun::sole()->status)->toBe('publish_failed');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/repos/'));
})->with(['revoked', 'baseMoved']);

it('recovers an unknown PR outcome without another model call or duplicate publication', function (): void {
    $this->losePrResponse = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    $run = CopilotSdkTestRun::sole();
    expect($run->status)->toBe('publish_failed')->and($run->error)->not->toContain('sensitive');
    $this->losePrResponse = false;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true, '--resume' => $run->run_id])->assertSuccessful();
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    expect($run->fresh()->status)->toBe('pull_request_open');
    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/pulls')))->toHaveCount(1);
});

it('rejects symlinks and incomplete trees before starting the SDK', function (string $condition): void {
    if ($condition === 'symlink') {
        $this->treeMode = '120000';
    } else {
        $this->truncatedTree = true;
    }
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    Process::assertNothingRan();
})->with(['symlink', 'truncated']);

it('rejects unsafe or out of project paths', function (string $path): void {
    $this->repository->update(['project_path' => 'public']);
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--path' => $path, '--publish' => true])->assertFailed();
    Process::assertNothingRan();
})->with(['../index.html', '/index.html', 'index.html', '.github/workflows/index.html', 'public/../index.html']);

it('rejects ordinary users and disabled SDK execution before a paid run', function (string $condition): void {
    if ($condition === 'user') {
        $this->admin->update(['role' => User::ROLE_USER]);
    } else {
        config(['copilot_sdk.enabled' => false]);
    }
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    Process::assertNothingRan();
})->with(['user', 'disabled']);

it('does not run another task while the repository is locked', function (): void {
    $lock = Cache::lock('copilot-sdk-test:'.$this->repository->repository_id, 600);
    $lock->get();
    try {
        $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
        Process::assertNothingRan();
        expect(Cache::lock('copilot-sdk-test:'.$this->repository->repository_id, 600)->get())->toBeFalse();
    } finally {
        $lock->release();
    }
});

it('independently escapes the approved title and preserves every other byte', function (): void {
    expect(app(CopilotSdkTitleRunner::class)->expected('<title>Old</title>\n<p>Keep</p>', 'A & B < C'))
        ->toBe('<title>A &amp; B &lt; C</title>\n<p>Keep</p>');
});

it('refuses to overwrite a branch changed outside the saved run', function (): void {
    $this->losePrResponse = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    $run = CopilotSdkTestRun::sole();
    $this->commitSha = str_repeat('f', 40);
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true, '--resume' => $run->run_id])->assertFailed();
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    expect($run->fresh()->commit_sha)->not->toBe($this->commitSha);
    Http::assertNotSent(fn (Request $request) => $request->method() === 'PATCH');
});

it('rejects publishing after the website connection is replaced', function (): void {
    $this->losePrResponse = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    $run = CopilotSdkTestRun::sole();
    $this->repository->update(['repository_id' => $this->repository->repository_id + 1]);
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true, '--resume' => $run->run_id])->assertFailed();
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/pulls')))->toHaveCount(1);
});

it('does not let a different administrator resume someone elses run', function (): void {
    $this->losePrResponse = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    $run = CopilotSdkTestRun::sole();
    $otherAdmin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--user' => $otherAdmin->id, '--publish' => true, '--resume' => $run->run_id])->assertFailed();
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    expect($run->fresh()->requested_by)->toBe($this->admin->id);
});

it('skips the model and publishing when the requested title already exists', function (): void {
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--title' => 'Home', '--publish' => true])->assertSuccessful();
    Process::assertNothingRan();
    expect(CopilotSdkTestRun::count())->toBe(0);
});

it('reconciles a closed PR without reopening or duplicating it', function (): void {
    $this->losePrResponse = true;
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertFailed();
    $run = CopilotSdkTestRun::sole();
    $this->prState = 'closed';
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true, '--resume' => $run->run_id])->assertSuccessful();
    expect($run->fresh()->status)->toBe('pull_request_closed');
    Process::assertRanTimes(fn (PendingProcess $process): bool => true, 1);
    expect(Http::recorded(fn (Request $request) => $request->method() === 'POST' && str_ends_with($request->url(), '/pulls')))->toHaveCount(1);
});

it('reports and persists safe worker failures instead of calling them title mismatches', function (string $code, string $message): void {
    Process::fake(function (PendingProcess $process) use ($code) {
        $request = json_decode($process->input, true);

        return Process::result(output: json_encode([
            'protocolVersion' => 1, 'runId' => $request['runId'], 'fixture' => 'repository-title', 'mode' => 'live',
            'status' => 'failed', 'error' => $code, 'elapsedMs' => 60000, 'toolCalls' => 2,
            'message' => 'private-provider-key',
        ]), exitCode: 1);
    });
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])
        ->expectsOutputToContain($message)
        ->doesntExpectOutputToContain('private-provider-key')->assertFailed();
    expect(CopilotSdkTestRun::sole()->error)->toContain($message)->toContain('Elapsed: 60000 ms; tool calls: 2.')->not->toContain('private-provider-key');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/repos/'));
})->with([
    'time limit' => ['time_limit', '60-second time limit'],
    'token limit' => ['token_limit', 'reported token limit'],
    'rate limit' => ['provider_rate_limit', 'rate-limited'],
    'authentication' => ['provider_authentication', 'rejected the API key'],
    'unknown error is never echoed' => ['private-provider-key', 'execution failed before validation'],
]);

it('distinguishes a model that made no edits from an invalid title change', function (): void {
    Process::fake(function (PendingProcess $process) {
        $request = json_decode($process->input, true);

        return Process::result(output: json_encode([
            'protocolVersion' => 1, 'runId' => $request['runId'], 'fixture' => 'repository-title', 'mode' => 'live',
            'status' => 'validation_failed', 'changes' => [], 'elapsedMs' => 2000, 'toolCalls' => 1,
        ]), exitCode: 1);
    });
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])
        ->expectsOutputToContain('SDK finished without editing the file.')->assertFailed();
});

it('gives the repository test a bounded budget for multiple model turns', function (): void {
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])->assertSuccessful();
    Process::assertRan(fn (PendingProcess $process): bool => json_decode($process->input, true)['limits']['maxTokens'] === 30000);
});

it('retains reported usage when a model run reaches its token limit', function (): void {
    Process::fake(function (PendingProcess $process) {
        $request = json_decode($process->input, true);

        return Process::result(output: json_encode([
            'protocolVersion' => 1, 'runId' => $request['runId'], 'fixture' => 'repository-title', 'mode' => 'live',
            'status' => 'failed', 'error' => 'token_limit', 'elapsedMs' => 43271, 'toolCalls' => 4,
            'usage' => ['inputTokens' => 25000, 'outputTokens' => 5500, 'cacheReadTokens' => 1000, 'cacheWriteTokens' => 0, 'events' => 4],
        ]), exitCode: 1);
    });
    $this->artisan('copilot-sdk:test-repository', [...$this->commands, '--publish' => true])
        ->expectsOutputToContain('reported token limit of 30000')->assertFailed();
    $run = CopilotSdkTestRun::sole();
    expect($run->usage)->toMatchArray(['inputTokens' => 25000, 'outputTokens' => 5500, 'events' => 4])
        ->and($run->error)->toContain('Reported input/output: 25000/5500; cache read/write: 1000/0.');
    Http::assertNotSent(fn (Request $request) => $request->method() === 'POST' && str_contains($request->url(), '/repos/'));
});
