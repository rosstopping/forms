<?php

namespace App\Jobs;

use App\Models\Website;
use App\Services\SeoWinDetector;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DetectSeoWins implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 120;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public Website $website) {}

    public function uniqueId(): string
    {
        return (string) $this->website->id;
    }

    public function handle(SeoWinDetector $detector): void
    {
        if ($website = $this->website->fresh()) {
            $detector->detect($website);
        }
    }
}
