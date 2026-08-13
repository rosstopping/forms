<?php

namespace App\Jobs;

use App\Models\User;
use App\Models\WebsiteHealthReportPage;
use App\Services\PixelOptimisationGenerator;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class GeneratePagePixelOptimisations implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public int $uniqueFor = 600;

    /** @var array<int, int> */
    public array $backoff = [30, 120];

    public function __construct(public WebsiteHealthReportPage $page, public User $author) {}

    public function uniqueId(): string
    {
        return (string) $this->page->id;
    }

    public function handle(PixelOptimisationGenerator $generator): void
    {
        $page = $this->page->fresh(['report.website']);

        if ($page?->report?->website) {
            $generator->generate($page->report->website, $page, $this->author);
        }
    }
}
