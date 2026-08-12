<?php

namespace App\Jobs;

use App\Models\ProspectDiscovery;
use App\Services\OpenStreetMapProspectFinder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class DiscoverProspects implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public function __construct(public ProspectDiscovery $discovery) {}

    public function uniqueId(): string
    {
        return (string) $this->discovery->id;
    }

    /** @return array<int, int> */
    public function backoff(): array
    {
        return [15, 60];
    }

    public function handle(OpenStreetMapProspectFinder $finder): void
    {
        $this->discovery->update(['status' => 'running', 'error' => null, 'started_at' => now()]);

        try {
            $candidates = $finder->find($this->discovery->area, $this->discovery->business_type);

            foreach ($candidates as $candidate) {
                $this->discovery->candidates()->updateOrCreate(['source_key' => $candidate['source_key']], $candidate);
            }

            $this->discovery->update(['status' => 'completed', 'candidate_count' => count($candidates), 'completed_at' => now()]);
        } catch (Throwable $exception) {
            $this->discovery->update(['status' => 'failed', 'error' => $exception->getMessage(), 'completed_at' => now()]);

            throw $exception;
        }
    }
}
