<?php

namespace App\Jobs;

use App\Models\ContentRequest;
use App\Models\User;
use App\Services\ContentRequestPixelOptimisationGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class GenerateContentRequestPixelOptimisations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public ContentRequest $contentRequest, public User $author) {}

    public function uniqueId(): string
    {
        return (string) $this->contentRequest->id;
    }

    /**
     * Execute the job.
     */
    public function handle(ContentRequestPixelOptimisationGenerator $generator): void
    {
        Cache::lock('content-request-work-'.$this->contentRequest->id, 180)->block(5, function () use ($generator): void {
            $contentRequest = $this->contentRequest->fresh();

            if (! $contentRequest || $contentRequest->pixel_processed_at || $contentRequest->manual_started_at) {
                return;
            }

            $generator->generate($contentRequest, $this->author);
        });
    }

    public function failed(?Throwable $exception): void
    {
        $this->contentRequest->update([
            'pixel_processed_at' => now(),
            'pixel_error' => $exception?->getMessage() ?? 'The Pixel draft could not be prepared.',
        ]);
    }
}
