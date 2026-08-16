<?php

namespace App\Jobs;

use App\Ai\Agents\WebsiteDataAssistant;
use App\Models\WebsiteAiQuestion;
use App\Services\WebsiteAiContext;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\RateLimitedException;
use Throwable;

class ProcessWebsiteAiQuestion implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 90;

    /** @var array<int, int> */
    public array $backoff = [15, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(public int $questionId) {}

    /**
     * Execute the job.
     */
    public function handle(WebsiteAiContext $context): void
    {
        $question = WebsiteAiQuestion::query()->with(['website', 'user'])->findOrFail($this->questionId);

        if ($question->status !== 'processing') {
            return;
        }

        $history = WebsiteAiQuestion::query()
            ->whereBelongsTo($question->website)
            ->whereBelongsTo($question->user)
            ->whereKeyNot($question->id)
            ->where('status', 'completed')
            ->latest()->limit(5)->get(['question', 'answer'])->reverse()->values()->toArray();
        $prompt = (string) json_encode([
            'previous_conversation' => $history,
            'current_question' => $question->question,
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $response = (new WebsiteDataAssistant($question->website->name, $context->for($question->website, $question->question)))
            ->prompt($prompt, timeout: 60);

        $question->update(['answer' => $response['answer'], 'status' => 'completed']);
    }

    public function failed(?Throwable $exception): void
    {
        $question = WebsiteAiQuestion::query()->find($this->questionId);

        if (! $question || $question->status !== 'processing') {
            return;
        }

        $rateLimited = $exception instanceof RateLimitedException;
        $question->update([
            'status' => 'failed',
            'error' => $rateLimited
                ? "The AI service is temporarily busy. This request has been returned to your allowance. Reference: WAI-{$question->id}."
                : "The assistant could not answer this question. Reference: WAI-{$question->id}.",
            'failure_type' => $exception ? class_basename($exception) : 'UnknownException',
            'failure_detail' => $this->safeFailureDetail($exception),
            'credited_at' => $rateLimited ? now() : null,
        ]);
        Log::error('Website data assistant queued request failed.', [
            'website_ai_question_id' => $question->id,
            'website_id' => $question->website_id,
            'user_id' => $question->user_id,
            'exception' => $exception,
        ]);
    }

    public function uniqueId(): string
    {
        return (string) $this->questionId;
    }

    protected function safeFailureDetail(?Throwable $exception): string
    {
        if (! $exception) {
            return 'No provider detail was available.';
        }

        $requestException = $exception instanceof RequestException
            ? $exception
            : ($exception->getPrevious() instanceof RequestException ? $exception->getPrevious() : null);
        $detail = $requestException
            ? 'HTTP '.$requestException->response->status().': '.(string) ($requestException->response->json('error.message') ?: 'The AI provider rejected the request.')
            : $exception->getMessage();
        $redacted = preg_replace([
            '/\bBearer\s+\S+/i', '/\bsk-[A-Za-z0-9_-]{10,}/', '/([?&](?:token|key|secret)=)[^&\s]+/i',
        ], ['Bearer [redacted]', '[redacted]', '$1[redacted]'], $detail) ?? 'No provider detail was available.';

        return Str::limit($redacted, 2000);
    }
}
