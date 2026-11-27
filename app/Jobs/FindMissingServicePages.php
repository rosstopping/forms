<?php

namespace App\Jobs;

use App\Services\ServicePageFinder;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Throwable;

class FindMissingServicePages implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 240;

    public int $uniqueFor = 300;

    public function __construct(public string $id, public string $url) {}

    public function uniqueId(): string
    {
        return $this->id;
    }

    public function handle(ServicePageFinder $finder): void
    {
        $key = 'service-page-finder:'.$this->id;
        $state = Cache::get($key);
        if (! is_array($state) || $state['status'] !== 'queued' || $state['started_at'] < now()->subMinutes(10)->timestamp) {
            return;
        }
        Cache::put($key, [...$state, 'status' => 'running'], now()->addDay());
        $result = $finder->cachedFind($this->url);
        Cache::put($key, [...$state, 'status' => 'completed', 'result' => $result], now()->addDay());
    }

    public function failed(?Throwable $exception): void
    {
        $key = 'service-page-finder:'.$this->id;
        $state = Cache::get($key);
        if (is_array($state)) {
            Cache::put($key, [...$state, 'status' => 'failed'], now()->addDay());
        }
    }
}
