<?php

use App\Jobs\GenerateSeoIntelligence;
use App\Models\SeoSnapshot;
use App\Services\SeoIntelligence\SeoSnapshotService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

test('the unique job processes its snapshot through the domain service', function (): void {
    $snapshot = SeoSnapshot::factory()->pending()->create();
    $service = $this->mock(SeoSnapshotService::class);
    $service->shouldReceive('process')->once()->withArgs(fn (SeoSnapshot $received): bool => $received->is($snapshot))->andReturn($snapshot);
    $job = new GenerateSeoIntelligence($snapshot);

    $job->handle($service);

    expect($job->uniqueId())->toBe((string) $snapshot->website_id)
        ->and($job->tries)->toBe(3)
        ->and($job->timeout)->toBeLessThan((int) config('queue.connections.database.retry_after'));
});

test('the job records a safe terminal failure', function (): void {
    $snapshot = SeoSnapshot::factory()->pending()->create();
    $job = new GenerateSeoIntelligence($snapshot);

    $job->failed(new RuntimeException('Sensitive provider response'));

    $snapshot->refresh();
    expect($snapshot->status)->toBe(SeoSnapshot::STATUS_FAILED)
        ->and($snapshot->errors['generation'])->not->toContain('Sensitive provider response')
        ->and($snapshot->completed_at)->not->toBeNull();
});
