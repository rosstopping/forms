<?php

use App\Services\CopilotSdkFixtureRunner;
use Illuminate\Process\PendingProcess;
use Illuminate\Support\Facades\Process;

function fakeSdkFixtureResult(PendingProcess $process, array $overrides = []): array
{
    $request = json_decode($process->input, true);

    return array_replace([
        'protocolVersion' => 1,
        'runId' => $request['runId'],
        'fixture' => $request['fixture'],
        'mode' => $request['mode'],
        'status' => $request['mode'] === 'probe' ? 'runtime_ready' : 'validated',
        'verification' => [['name' => 'title-only-change', 'passed' => true]],
        'changes' => [['path' => 'index.html', 'before' => 'Home', 'after' => 'Acme Plumbing | Doncaster']],
        'usage' => ['inputTokens' => 10, 'outputTokens' => 5],
        'elapsedMs' => 50,
    ], $overrides);
}

it('defaults to a free simulation without SDK credentials', function (): void {
    Process::fake(fn (PendingProcess $process) => Process::result(output: json_encode(fakeSdkFixtureResult($process))));
    config(['copilot_sdk.enabled' => false, 'copilot_sdk.api_key' => 'private-key']);

    $this->artisan('copilot-sdk:verify')->expectsOutput('Fixture simulation passed. The SDK and model were not invoked.')->assertSuccessful();
    Process::assertRan(fn (PendingProcess $process): bool => is_array($process->command)
        && $process->environment['SITEWELL_MODEL_API_KEY'] === false
        && ! str_contains($process->input, 'private-key')
        && json_decode($process->input, true)['mode'] === 'dry-run');
});

it('keeps runtime and paid calls disabled until explicitly configured', function (string $option): void {
    Process::fake();
    config(['copilot_sdk.enabled' => false]);
    $this->artisan('copilot-sdk:verify', [$option => true])->assertFailed();
    Process::assertNothingRan();
})->with(['--probe', '--live']);

it('requires model configuration before starting a paid task', function (): void {
    Process::fake();
    config(['copilot_sdk.enabled' => true, 'copilot_sdk.api_key' => null, 'copilot_sdk.model' => null]);
    $this->artisan('copilot-sdk:verify', ['--live' => true])->assertFailed();
    Process::assertNothingRan();
});

it('rejects conflicting verification modes', function (): void {
    Process::fake();
    $this->artisan('copilot-sdk:verify', ['--live' => true, '--probe' => true])->assertFailed();
    Process::assertNothingRan();
});

it('passes credentials only through the live worker environment', function (): void {
    Process::fake(fn (PendingProcess $process) => Process::result(output: json_encode(fakeSdkFixtureResult($process))));
    config(['copilot_sdk.enabled' => true, 'copilot_sdk.api_key' => 'private-key', 'copilot_sdk.model' => 'test-model']);
    app(CopilotSdkFixtureRunner::class)->run('live');
    Process::assertRan(fn (PendingProcess $process): bool => $process->environment['SITEWELL_MODEL_API_KEY'] === 'private-key'
        && ! str_contains(json_encode($process->command), 'private-key')
        && ! str_contains($process->input, 'private-key'));
});

it('probes the runtime without passing a model key', function (): void {
    Process::fake(fn (PendingProcess $process) => Process::result(output: json_encode(fakeSdkFixtureResult($process))));
    config(['copilot_sdk.enabled' => true, 'copilot_sdk.api_key' => 'private-key']);
    $this->artisan('copilot-sdk:verify', ['--probe' => true])->assertSuccessful();
    Process::assertRan(fn (PendingProcess $process): bool => $process->environment['SITEWELL_MODEL_API_KEY'] === false);
});

it('rejects stale malformed and unsuccessful worker results', function (array $overrides, int $exitCode): void {
    Process::fake(fn (PendingProcess $process) => Process::result(output: json_encode(fakeSdkFixtureResult($process, $overrides)), exitCode: $exitCode));
    expect(fn () => app(CopilotSdkFixtureRunner::class)->run())->toThrow(RuntimeException::class);
})->with([
    'wrong run' => [['runId' => 'some-other-run'], 0],
    'wrong protocol' => [['protocolVersion' => 99], 0],
    'wrong mode' => [['mode' => 'live'], 0],
    'wrong fixture' => [['fixture' => 'customer-repository'], 0],
    'nonzero exit' => [[], 1],
    'failed validation' => [['status' => 'validation_failed'], 0],
    'probe masquerading as validation' => [['status' => 'runtime_ready'], 0],
    'missing checks' => [['verification' => []], 0],
]);

it('does not expose process errors or raw output', function (): void {
    Process::fake(['*' => Process::result(output: 'private-key invalid JSON', errorOutput: 'private-key', exitCode: 1)]);
    $this->artisan('copilot-sdk:verify')
        ->expectsOutput('The SDK fixture worker could not finish. Check the worker installation and timeout.')
        ->doesntExpectOutputToContain('private-key')->assertFailed();
});
