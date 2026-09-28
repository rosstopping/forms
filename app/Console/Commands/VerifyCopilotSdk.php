<?php

namespace App\Console\Commands;

use App\Services\CopilotSdkFixtureRunner;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('copilot-sdk:verify {--probe : Start and ping the SDK runtime without a model request} {--live : Run the synthetic fixture using the configured paid model}')]
#[Description('Verify the SDK worker using a synthetic website fixture; defaults to a no-cost simulation')]
class VerifyCopilotSdk extends Command
{
    public function handle(CopilotSdkFixtureRunner $runner): int
    {
        if ($this->option('probe') && $this->option('live')) {
            $this->error('Choose either --probe or --live.');

            return self::FAILURE;
        }

        $mode = $this->option('live') ? 'live' : ($this->option('probe') ? 'probe' : 'dry-run');
        try {
            $result = $runner->run($mode);
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $this->info(match ($mode) {
            'live' => 'SDK model fixture passed. No customer repository was accessed.',
            'probe' => 'SDK runtime responded. No model request was made.',
            default => 'Fixture simulation passed. The SDK and model were not invoked.',
        });
        $this->line('Run: '.$result['runId']);
        $this->line('Elapsed: '.($result['elapsedMs'] ?? 0).' ms');
        $this->line('Reported input/output tokens: '.data_get($result, 'usage.inputTokens', 0).'/'.data_get($result, 'usage.outputTokens', 0));

        return self::SUCCESS;
    }
}
