<?php

namespace App\Jobs;

use App\Models\SearchConsoleConnection;
use App\Models\SearchOpportunity;
use App\Services\SearchConsoleClient;
use App\Services\SearchOpportunityFinder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DiscoverSearchOpportunities implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    /**
     * Create a new job instance.
     */
    public int $tries = 3;

    public int $uniqueFor = 3600;

    public int $timeout = 120;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    public function __construct(public SearchConsoleConnection $searchConsoleConnection) {}

    public function uniqueId(): string
    {
        return (string) $this->searchConsoleConnection->id;
    }

    /**
     * Execute the job.
     */
    public function handle(SearchConsoleClient $searchConsole, SearchOpportunityFinder $finder): void
    {
        $end = now()->subDay()->startOfDay();
        $start = $end->copy()->subDays(27);
        $previousEnd = $start->copy()->subDay();
        $previousStart = $previousEnd->copy()->subDays(27);

        try {
            $current = $searchConsole->queryPagePerformanceForPeriod($this->searchConsoleConnection, $start, $end);
            $previous = $searchConsole->queryPagePerformanceForPeriod($this->searchConsoleConnection, $previousStart, $previousEnd);
            $detectedAt = now();
            $fingerprints = [];

            foreach ($finder->find($current, $previous) as $data) {
                $fingerprints[] = $data['fingerprint'];
                $opportunity = SearchOpportunity::query()->firstOrNew([
                    'website_id' => $this->searchConsoleConnection->website_id,
                    'fingerprint' => $data['fingerprint'],
                ]);
                $opportunity->fill([...$data, 'last_detected_at' => $detectedAt]);
                $opportunity->first_detected_at ??= $detectedAt;
                if (! $opportunity->exists || $opportunity->status === SearchOpportunity::STATUS_RESOLVED) {
                    $opportunity->status = SearchOpportunity::STATUS_OPEN;
                }
                $opportunity->save();
            }

            $this->searchConsoleConnection->website->searchOpportunities()
                ->where('status', SearchOpportunity::STATUS_OPEN)
                ->when($fingerprints !== [], fn ($query) => $query->whereNotIn('fingerprint', $fingerprints))
                ->update(['status' => SearchOpportunity::STATUS_RESOLVED]);

            $this->searchConsoleConnection->update(['opportunities_checked_at' => $detectedAt, 'opportunities_error' => null]);
        } catch (Throwable $exception) {
            $this->searchConsoleConnection->update(['opportunities_error' => $exception->getMessage()]);
            throw $exception;
        }
    }

    public function failed(?Throwable $exception): void
    {
        $this->searchConsoleConnection->update(['opportunities_error' => $exception?->getMessage() ?: 'Search opportunity discovery failed.']);
    }
}
