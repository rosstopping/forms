<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class CopilotSdkFixtureRunner
{
    /** @return array<string, mixed> */
    public function run(string $mode = 'dry-run'): array
    {
        if (! in_array($mode, ['dry-run', 'probe', 'live'], true)) {
            throw new RuntimeException('Unknown SDK verification mode.');
        }
        if ($mode !== 'dry-run' && ! config('copilot_sdk.enabled')) {
            throw new RuntimeException('Enable COPILOT_SDK_ENABLED before starting the SDK runtime.');
        }
        if ($mode === 'live' && (! filled(config('copilot_sdk.api_key')) || ! filled(config('copilot_sdk.model')))) {
            throw new RuntimeException('Configure COPILOT_SDK_API_KEY and COPILOT_SDK_MODEL before a live fixture run.');
        }

        $request = [
            'protocolVersion' => 1,
            'runId' => (string) Str::uuid(),
            'fixture' => 'website-title',
            'mode' => $mode,
            'limits' => [
                'timeoutSeconds' => max(1, min(120, (int) config('copilot_sdk.timeout_seconds'))),
                'maxToolCalls' => max(1, min(20, (int) config('copilot_sdk.max_tool_calls'))),
                'maxTokens' => max(1, min(50000, (int) config('copilot_sdk.max_tokens'))),
            ],
        ];

        try {
            $process = Process::path(resource_path('copilot-worker'))
                ->timeout($request['limits']['timeoutSeconds'] + 10)
                ->env([
                    'SITEWELL_MODEL_PROVIDER' => $mode === 'live' ? (string) config('copilot_sdk.provider') : false,
                    'SITEWELL_MODEL_NAME' => $mode === 'live' ? (string) config('copilot_sdk.model') : false,
                    'SITEWELL_MODEL_API_KEY' => $mode === 'live' ? (string) config('copilot_sdk.api_key') : false,
                ])
                ->input(json_encode($request, JSON_THROW_ON_ERROR))
                ->run([(string) config('copilot_sdk.node_binary'), resource_path('copilot-worker/src/cli.mjs')]);
            $result = json_decode($process->output(), true, 32, JSON_THROW_ON_ERROR);
        } catch (Throwable) {
            throw new RuntimeException('The SDK fixture worker could not finish. Check the worker installation and timeout.');
        }

        if (! is_array($result) || ($result['protocolVersion'] ?? null) !== 1
            || ($result['runId'] ?? null) !== $request['runId'] || ($result['mode'] ?? null) !== $mode
            || ($result['fixture'] ?? null) !== $request['fixture']) {
            throw new RuntimeException('The SDK fixture worker returned an invalid result.');
        }
        $expectedStatus = $mode === 'probe' ? 'runtime_ready' : 'validated';
        if (! $process->successful() || ($result['status'] ?? null) !== $expectedStatus
            || ($mode !== 'probe' && data_get($result, 'verification.0.passed') !== true)) {
            throw new RuntimeException('The SDK fixture did not pass. No repository has been changed.');
        }

        return $result;
    }
}
