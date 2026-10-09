<?php

use App\Jobs\MeasureSeoImpact;
use App\Models\SearchConsoleConnection;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Services\SearchConsoleClient;
use App\Services\SeoImpactAutomation;
use App\Services\SeoImpactCheckpoints;
use App\Services\SeoImpactEvaluator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Queue::fake();
    Http::preventStrayRequests();
    $this->travelTo(Carbon::parse('2026-10-01 12:00:00 UTC'));
    $owner = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->website = Website::factory()->for($owner, 'owner')->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
});

test('new delivery selects fourteen thirty sixty ninety without changing existing live tracking', function (): void {
    $impact = SeoImpact::factory()->for($this->website)->create();
    $automation = app(SeoImpactAutomation::class);
    $automation->activate($impact, now(), 'Published', 'Improved useful content', ['https://example.com/services']);
    expect($impact->fresh()->measurement_checkpoints)->toBe([14, 30, 60, 90])->and($impact->fresh()->review_after_days)->toBe(14);
    $legacy = SeoImpact::factory()->for($this->website)->create(['status' => 'measuring', 'live_at' => now()->subDays(10), 'review_after_days' => 28]);
    $automation->activate($legacy, now(), 'Duplicate event', 'Updated', ['https://example.com/services']);
    expect($legacy->fresh()->measurement_checkpoints)->toBeNull()->and(app(SeoImpactCheckpoints::class)->next($legacy))->toBe(56);
});

test('checkpoints compare equal windows freeze both baselines and complete only at ninety days', function (): void {
    $live = Carbon::parse('2026-08-01 12:00:00 UTC');
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => $live, 'review_after_days' => 14, 'measurement_checkpoints' => [14, 30, 60, 90], 'control_url' => null]);
    $calls = [];
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->andReturnUsing(function ($connection, $start, $end) use (&$calls): array {
        $days = (int) $start->diffInDays($end) + 1;
        $calls[] = [$start->toDateString(), $end->toDateString(), $days];

        return ['complete' => true, 'finalized' => true, 'rows' => [], 'totals' => ['clicks' => $days * 10, 'impressions' => $days * 100, 'ctr' => .1, 'position' => 8, 'reported_days' => $days]];
    });
    foreach ([14, 30, 60, 90] as $checkpoint) {
        $this->travelTo($live->copy()->addDays($checkpoint + 4));
        (new MeasureSeoImpact($impact))->handle($client, app(SeoImpactEvaluator::class));
        $impact->refresh();
        $review = $impact->reviews()->where('checkpoint', $checkpoint)->sole();
        expect($review->baseline['end'])->toBe('2026-07-31')
            ->and((int) Carbon::parse($review->baseline['start'])->diffInDays(Carbon::parse($review->baseline['end'])) + 1)->toBe(min(28, $checkpoint))
            ->and((int) $review->period_start->diffInDays($review->period_end) + 1)->toBe(min(28, $checkpoint));
        expect($impact->status)->toBe($checkpoint === 90 ? 'completed' : 'measuring');
    }
    expect($calls)->toHaveCount(6)->and($impact->reviews()->count())->toBe(4)->and($impact->next_measurement_at)->toBeNull()
        ->and($impact->automatic_summary)->toContain('Day 90');
    (new MeasureSeoImpact($impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($calls)->toHaveCount(6)->and($impact->reviews()->count())->toBe(4);
});

test('fourteen day review waits for reporting lag and does not compare a partial window', function (): void {
    $live = now()->subDays(15);
    $impact = SeoImpact::factory()->for($this->website)->create(['automated' => true, 'status' => 'measuring', 'live_at' => $live, 'review_after_days' => 14, 'measurement_checkpoints' => [14, 30, 60, 90]]);
    $client = $this->mock(SearchConsoleClient::class);
    $client->shouldReceive('impactPerformance')->andReturn(['complete' => false, 'finalized' => true, 'rows' => [], 'totals' => null]);
    (new MeasureSeoImpact($impact))->handle($client, app(SeoImpactEvaluator::class));
    expect($impact->reviews()->count())->toBe(0)->and($impact->fresh()->review_after_days)->toBe(14)
        ->and($impact->fresh()->next_measurement_at->gt(now()))->toBeTrue();
});
