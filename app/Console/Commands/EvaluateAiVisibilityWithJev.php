<?php

namespace App\Console\Commands;

use App\Models\AiVisibilityResult;
use App\Services\JevAiVisibilityEvaluator;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

#[Signature('jev:evaluate-ai-visibility {--limit=50 : Maximum observations to inspect (1-500)} {--after-id=0 : Inspect observations after this result ID} {--website= : Restrict to one website ID}')]
#[Description('Evaluate saved AI Visibility evidence in shadow mode without changing customer decisions')]
class EvaluateAiVisibilityWithJev extends Command
{
    public function handle(JevAiVisibilityEvaluator $evaluator): int
    {
        if ($error = $evaluator->configurationError()) {
            $this->error($error);

            return self::FAILURE;
        }
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 500]]);
        $afterId = filter_var($this->option('after-id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 0]]);
        $websiteId = $this->option('website') === null ? null : filter_var($this->option('website'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($limit === false || $afterId === false || $websiteId === false) {
            $this->error('Use --limit=1..500, --after-id=0 or higher, and a positive --website ID.');

            return self::INVALID;
        }

        $lock = Cache::lock('jev-ai-visibility-shadow', 360);
        if (! $lock->get()) {
            $this->error('Another Jev shadow evaluation batch is running.');

            return self::FAILURE;
        }
        try {
            $deadline = hrtime(true) + max(1, min(300, (int) config('jev.max_run_seconds'))) * 1000000000;
            $byteBudget = max(0, min(2000000, (int) config('jev.max_batch_bytes')));
            $completed = $failed = $skipped = $inspected = 0;
            $lastId = $afterId;
            $query = AiVisibilityResult::query()->where('status', 'completed')->whereNotNull('response_text')
                ->where('id', '>', $afterId)->when($websiteId, fn ($query) => $query->where('website_id', $websiteId))
                ->orderBy('id')->limit($limit);

            foreach ($query->cursor() as $result) {
                $remainingSeconds = (int) ceil(($deadline - hrtime(true)) / 1000000000);
                if ($remainingSeconds < 1) {
                    $this->warn('Runtime limit reached. Resume using the last inspected ID.');

                    break;
                }
                $request = $evaluator->requestFor($result);
                $bytes = $request === null ? 0 : strlen(json_encode($request, JSON_THROW_ON_ERROR));
                if ($request !== null && $bytes <= min(40000, (int) config('jev.max_request_bytes')) && ! $evaluator->existing($result, $request)) {
                    if ($bytes > $byteBudget) {
                        $this->warn('Request-byte budget reached. Resume using the last inspected ID.');

                        break;
                    }
                    $byteBudget -= $bytes;
                    $evaluation = $evaluator->evaluate($result, $remainingSeconds);
                    if ($evaluation?->status === 'completed') {
                        $completed++;
                    } elseif ($evaluation?->status === 'failed') {
                        $failed++;
                        $this->warn("Result {$result->id}: {$evaluation->error_type}.");
                    } else {
                        $skipped++;
                    }
                } else {
                    $skipped++;
                }
                $inspected++;
                $lastId = $result->id;
            }
            $this->info("Inspected {$inspected}; completed {$completed}; failed {$failed}; skipped {$skipped}. Last inspected ID: {$lastId}.");
            $this->line('Shadow records only. Failures and interrupted reservations are not automatically retried; customer visibility is unchanged.');

            return $failed > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
