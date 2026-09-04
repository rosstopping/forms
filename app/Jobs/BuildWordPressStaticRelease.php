<?php

namespace App\Jobs;

use App\Models\WordpressStaticRelease;
use App\Services\WordPressDeploymentNotifier;
use App\Services\WordPressStaticReleaseBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Str;
use Throwable;

class BuildWordPressStaticRelease implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 180;

    /** @var int[] */
    public array $backoff = [30, 120];

    public function __construct(public int $releaseId) {}

    /**
     * Execute the job.
     */
    public function handle(WordPressStaticReleaseBuilder $builder, WordPressDeploymentNotifier $notifier): void
    {
        $release = WordpressStaticRelease::query()
            ->with(['website.repository.installation', 'website.wordpressConnection'])
            ->findOrFail($this->releaseId);

        if ($release->status === WordpressStaticRelease::STATUS_READY) {
            return;
        }

        $repository = $release->website->repository;

        if (! $repository) {
            throw new \RuntimeException('The website no longer has a connected GitHub repository.');
        }

        try {
            $builder->build($release, $repository);
            $connection = $release->website->wordpressConnection;

            if ($connection) {
                $notifier->notify($connection, $release->refresh());
            }
        } catch (Throwable $exception) {
            $release->update([
                'status' => WordpressStaticRelease::STATUS_FAILED,
                'error' => Str::limit($exception->getMessage(), 2000),
            ]);

            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        WordpressStaticRelease::query()->whereKey($this->releaseId)->update([
            'status' => WordpressStaticRelease::STATUS_FAILED,
            'error' => Str::limit($exception?->getMessage() ?? 'The release job stopped unexpectedly.', 2000),
        ]);
    }
}
