<?php

namespace App\Jobs;

use App\Models\WebsiteBuild;
use App\Services\WebsiteBuilder;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class BuildWebsite implements ShouldBeEncrypted, ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 3600;

    public bool $failOnTimeout = true;

    public function __construct(public int $websiteBuildId) {}

    public function uniqueId(): string
    {
        return (string) $this->websiteBuildId;
    }

    public function handle(WebsiteBuilder $builder): void
    {
        $build = WebsiteBuild::query()->findOrFail($this->websiteBuildId);

        if ($build->status === WebsiteBuild::STATUS_COMPLETED) {
            return;
        }

        $requester = $build->requester;

        if (! $requester) {
            throw new RuntimeException('The administrator who requested this website build no longer exists.');
        }

        $build->update([
            'status' => WebsiteBuild::STATUS_RUNNING,
            'error' => null,
            'started_at' => now(),
            'completed_at' => null,
        ]);

        $website = $builder->build($build->details, $requester);

        $build->update([
            'website_id' => $website->id,
            'status' => WebsiteBuild::STATUS_COMPLETED,
            'completed_at' => now(),
        ]);
    }

    public function failed(?Throwable $exception): void
    {
        WebsiteBuild::query()->find($this->websiteBuildId)?->update([
            'status' => WebsiteBuild::STATUS_FAILED,
            'error' => $exception?->getMessage() ?? 'The website build failed unexpectedly.',
            'completed_at' => now(),
        ]);
    }
}
