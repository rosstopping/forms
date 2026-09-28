<?php

namespace App\Services;

use App\Models\CopilotSdkTestRun;
use DomainException;
use Illuminate\Support\Facades\Process;
use Throwable;

class CopilotSdkTitleRunner
{
    public function ensureConfigured(): void
    {
        if (! config('copilot_sdk.enabled') || ! filled(config('copilot_sdk.api_key')) || ! filled(config('copilot_sdk.model'))) {
            throw new DomainException('Configure the SDK runtime, model and API key before a live test.');
        }
    }

    public function expected(string $original, string $title): string
    {
        if (strlen($original) > 8192 || ! mb_check_encoding($original, 'UTF-8')
            || preg_match_all('/<title>[^<]*<\/title>/', $original) !== 1
            || trim($title) === '' || mb_strlen($title) > 200 || preg_match('/[\x00-\x1f\x7f]/', $title)) {
            throw new DomainException('Use an HTML file under 8 KB with exactly one plain <title> tag and a title of 1–200 characters.');
        }

        $replacement = preg_replace_callback('/<title>[^<]*<\/title>/', fn (): string => '<title>'.htmlspecialchars($title, ENT_NOQUOTES | ENT_SUBSTITUTE, 'UTF-8').'</title>', $original);
        if (strlen($replacement) > 8192) {
            throw new DomainException('The updated file would exceed the 8 KB test limit.');
        }

        return $replacement;
    }

    /** @return array{replacement: string, usage: array<string, int>} */
    public function run(CopilotSdkTestRun $run): array
    {
        $this->ensureConfigured();
        $expected = $this->expected($run->original, $run->title);
        $request = [
            'protocolVersion' => 1, 'runId' => $run->run_id, 'fixture' => 'repository-title', 'mode' => 'live',
            'document' => ['path' => $run->path, 'original' => $run->original, 'title' => $run->title],
            'limits' => [
                'timeoutSeconds' => max(1, min(120, (int) config('copilot_sdk.timeout_seconds'))),
                'maxToolCalls' => max(1, min(20, (int) config('copilot_sdk.max_tool_calls'))),
                'maxTokens' => max(1, min(50000, (int) config('copilot_sdk.max_tokens'))),
            ],
        ];
        try {
            $process = Process::path(resource_path('copilot-worker'))->timeout($request['limits']['timeoutSeconds'] + 10)
                ->env([
                    'SITEWELL_MODEL_PROVIDER' => (string) config('copilot_sdk.provider'),
                    'SITEWELL_MODEL_NAME' => (string) config('copilot_sdk.model'),
                    'SITEWELL_MODEL_API_KEY' => (string) config('copilot_sdk.api_key'),
                ])->input(json_encode($request, JSON_THROW_ON_ERROR))
                ->run([(string) config('copilot_sdk.node_binary'), resource_path('copilot-worker/src/cli.mjs')]);
            $result = json_decode($process->output(), true, 32, JSON_THROW_ON_ERROR);
            $valid = $process->successful() && ($result['protocolVersion'] ?? null) === 1
                && ($result['runId'] ?? null) === $run->run_id && ($result['fixture'] ?? null) === 'repository-title'
                && ($result['mode'] ?? null) === 'live' && ($result['status'] ?? null) === 'validated'
                && data_get($result, 'verification.0.passed') === true
                && ($result['changes'] ?? null) === [['path' => $run->path, 'before' => $run->original, 'after' => $expected]];
        } catch (Throwable) {
            throw new DomainException('SDK worker failed. Check installation and model configuration; no branch was published.');
        }
        if (! $valid) {
            throw new DomainException('SDK output failed independent title-only validation; no branch was published.');
        }
        $usage = [];
        foreach (['inputTokens', 'outputTokens', 'cacheReadTokens', 'cacheWriteTokens', 'events'] as $key) {
            $usage[$key] = max(0, (int) data_get($result, 'usage.'.$key, 0));
        }

        return ['replacement' => $expected, 'usage' => $usage];
    }
}
