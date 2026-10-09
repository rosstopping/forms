<?php

use App\Jobs\SyncSearchConsoleDailyHistory;
use App\Models\SearchConsoleConnection;
use App\Models\SearchConsoleDailyMetric;
use App\Models\User;
use App\Models\Website;
use App\Services\ReportingPeriod;
use App\Services\SearchConsoleClient;
use App\Services\SearchConsoleDailyHistory;
use App\Services\SearchConsoleProgress;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-10-09 12:00:00', 'Europe/London'));
    Http::preventStrayRequests();
    Queue::fake();
    $this->website = Website::factory()->create();
    $this->connection = SearchConsoleConnection::factory()->for($this->website)->create(['property_url' => 'sc-domain:example.com']);
});

function reportingDay(SearchConsoleConnection $connection, string $date, array $attributes = []): SearchConsoleDailyMetric
{
    return SearchConsoleDailyMetric::factory()->create(['website_id' => $connection->website_id, 'search_console_connection_id' => $connection->id,
        'property_url' => $connection->property_url, 'date' => $date, ...$attributes]);
}

test('rolling reporting defaults to equal 28 day periods with a Pacific reporting delay', function (): void {
    $period = ReportingPeriod::fromInput([]);
    expect($period->start->toDateString())->toBe('2026-09-09')->and($period->end->toDateString())->toBe('2026-10-06')
        ->and($period->previousStart->toDateString())->toBe('2026-08-12')->and($period->previousEnd->toDateString())->toBe('2026-09-08');
    $this->travelTo(CarbonImmutable::parse('2026-10-09 00:30:00', 'Europe/London'));
    expect(ReportingPeriod::cutoff()->toDateString())->toBe('2026-10-05');
});

test('reporting presets and year comparisons retain equivalent day counts', function (string $preset): void {
    $period = ReportingPeriod::fromInput(['period' => $preset, 'comparison' => 'year']);
    expect($period->start->diffInDays($period->end))->toBe($period->previousStart->diffInDays($period->previousEnd))
        ->and($period->previousEnd->toDateString())->toBe('2025-10-06');
})->with(['7d', '28d', '3m', '6m', '12m']);

test('leap year and custom comparisons use equal inclusive ranges', function (): void {
    $period = ReportingPeriod::fromInput(['period' => 'custom', 'comparison' => 'year', 'start' => '2024-02-01', 'end' => '2024-02-29']);
    expect($period->previousEnd->toDateString())->toBe('2023-02-28')->and($period->previousStart->toDateString())->toBe('2023-01-31')
        ->and($period->previousStart->diffInDays($period->previousEnd))->toBe(28.0);
});

test('invalid custom periods cannot include unfinished days or unbounded ranges', function (array $input): void {
    expect(fn () => ReportingPeriod::fromInput($input))->toThrow(ValidationException::class);
})->with([
    [['period' => 'custom']],
    [['period' => 'custom', 'start' => '2026-10-01', 'end' => '2026-10-08']],
    [['period' => 'custom', 'start' => '2026-10-06', 'end' => '2026-10-01']],
    [['period' => 'custom', 'start' => '2024-01-01', 'end' => '2026-01-01']],
    [['period' => 'custom', 'start' => '2026-02-30', 'end' => '2026-03-01']],
]);

test('stored daily comparisons use weighted position and computed click rates without network reads', function (): void {
    reportingDay($this->connection, '2026-10-05', ['clicks' => 10, 'impressions' => 100, 'position' => 10]);
    reportingDay($this->connection, '2026-10-06', ['clicks' => 30, 'impressions' => 300, 'position' => 2]);
    reportingDay($this->connection, '2026-10-03', ['clicks' => 10]);
    reportingDay($this->connection, '2026-10-04', ['clicks' => 10]);
    $report = app(SearchConsoleProgress::class)->forWebsite($this->website, ['period' => 'custom', 'start' => '2026-10-05', 'end' => '2026-10-06']);
    expect($report['current']['complete'])->toBeTrue()->and($report['current']['position'])->toBe(4.0)
        ->and($report['current']['ctr'])->toBe(0.1)->and($report['click_change'])->toBe(100.0);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('missing days and zero baselines never manufacture percentage improvements', function (): void {
    reportingDay($this->connection, '2026-10-06', ['clicks' => 10]);
    reportingDay($this->connection, '2026-10-05', ['data_status' => 'no_data', 'clicks' => null, 'impressions' => null, 'position' => null]);
    $report = app(SearchConsoleProgress::class)->forWebsite($this->website, ['period' => 'custom', 'start' => '2026-10-05', 'end' => '2026-10-06']);
    expect($report['current']['complete'])->toBeFalse()->and($report['current']['reported_days'])->toBe(1)->and($report['click_change'])->toBeNull();
    reportingDay($this->connection, '2026-10-04', ['clicks' => 0]);
    $report = app(SearchConsoleProgress::class)->forWebsite($this->website, ['period' => 'custom', 'start' => '2026-10-06', 'end' => '2026-10-06']);
    expect($report['previous']['clicks'])->toBeNull()->and($report['click_change'])->toBeNull();
    SearchConsoleDailyMetric::whereDate('date', '2026-10-05')->update(['data_status' => 'observed', 'clicks' => 0, 'impressions' => 100, 'position' => 10]);
    $report = app(SearchConsoleProgress::class)->forWebsite($this->website, ['period' => 'custom', 'start' => '2026-10-06', 'end' => '2026-10-06']);
    expect($report['previous']['complete'])->toBeTrue()->and($report['click_change'])->toBeNull();
});

test('an incomplete provider tail moves rolling windows but stale imports do not silently change reporting dates', function (): void {
    reportingDay($this->connection, '2026-10-04');
    $report = app(SearchConsoleProgress::class)->forWebsite($this->website);
    expect($report['period']->end->toDateString())->toBe('2026-10-04');
    SearchConsoleDailyMetric::query()->delete();
    reportingDay($this->connection, '2026-09-01');
    expect(app(SearchConsoleProgress::class)->forWebsite($this->website)['period']->end->toDateString())->toBe('2026-10-06');
});

test('property changes isolate old reporting history', function (): void {
    reportingDay($this->connection, '2026-10-06');
    $this->connection->update(['property_url' => 'sc-domain:other.example']);
    $report = app(SearchConsoleProgress::class)->forWebsite($this->website->fresh());
    expect($report['current']['clicks'])->toBeNull()->and($report['last_date'])->toBeNull();
});

test('daily imports backfill once then refresh a bounded tail and retain missing versus provisional days', function (): void {
    $client = Mockery::mock(SearchConsoleClient::class);
    $client->shouldReceive('dailyPerformance')->once()->withArgs(fn ($connection, $start, $end) => $start->toDateString() === '2025-06-06' && $end->toDateString() === '2026-10-06')
        ->andReturn(['rows' => [['date' => '2026-10-04', 'clicks' => 2, 'impressions' => 10, 'position' => 4]], 'incomplete_from' => '2026-10-06']);
    $history = new SearchConsoleDailyHistory($client);
    $history->sync($this->connection);
    expect(SearchConsoleDailyMetric::whereDate('date', '2026-10-06')->exists())->toBeFalse()
        ->and(SearchConsoleDailyMetric::whereDate('date', '2026-10-05')->sole()->data_status)->toBe('no_data');
    $count = SearchConsoleDailyMetric::count();
    $client->shouldReceive('dailyPerformance')->once()->withArgs(fn ($connection, $start, $end) => $start->toDateString() === '2026-09-29')
        ->andReturn(['rows' => [['date' => '2026-10-04', 'clicks' => 3, 'impressions' => 10, 'position' => 3]], 'incomplete_from' => '2026-10-06']);
    $history->sync($this->connection);
    expect(SearchConsoleDailyMetric::count())->toBe($count)->and(SearchConsoleDailyMetric::whereDate('date', '2026-10-04')->sole()->clicks)->toBe(3);
});

test('a failed import leaves previous daily evidence intact', function (): void {
    $row = reportingDay($this->connection, '2026-10-06');
    $client = Mockery::mock(SearchConsoleClient::class);
    $client->shouldReceive('dailyPerformance')->once()->andThrow(new RuntimeException('Temporary provider failure'));
    expect(fn () => (new SearchConsoleDailyHistory($client))->sync($this->connection))->toThrow(RuntimeException::class);
    expect($row->fresh()->clicks)->toBe(10);
});

test('daily API imports make only site date requests and use provider finality metadata', function (): void {
    Http::fake(['*' => Http::sequence()->push(['rows' => [], 'metadata' => ['first_incomplete_date' => '2026-10-06']])
        ->push(['rows' => [['keys' => ['2026-10-05'], 'clicks' => 3, 'impressions' => 50, 'position' => 8]]])]);
    $result = app(SearchConsoleClient::class)->dailyPerformance($this->connection, now()->subDays(7), now()->subDays(3));
    expect($result['incomplete_from'])->toBe('2026-10-06')->and($result['rows'][0]['date'])->toBe('2026-10-05');
    Http::assertSentCount(2);
    Http::assertSent(fn ($request) => $request['dataState'] === 'final' && $request['dimensions'] === ['date'] && $request['type'] === 'web');
});

test('scheduled imports only queue active connected sites with access', function (): void {
    SearchConsoleConnection::factory()->create(['access_denied_at' => now()]);
    SearchConsoleConnection::factory()->for(Website::factory()->create(['is_active' => false]))->create();
    SearchConsoleConnection::factory()->create(['property_url' => null]);
    $this->artisan('search-console:sync-daily')->assertSuccessful();
    Queue::assertPushed(SyncSearchConsoleDailyHistory::class, 1);
    Http::assertNothingSent();
});

test('the website dashboard offers rolling reports without starting imports', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN, 'current_website_id' => $this->website->id]);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSuccessful()->assertSee('Last 28 days')->assertSee('Partial coverage')
        ->assertViewHas('searchProgress', fn ($report) => $report['period']->preset === '28d');
    Http::assertNothingSent();
    Queue::assertNothingPushed();
});

test('a selected property changing during an import cannot commit old property results', function (): void {
    $client = Mockery::mock(SearchConsoleClient::class);
    $connection = $this->connection;
    $client->shouldReceive('dailyPerformance')->once()->andReturnUsing(function () use ($connection): array {
        $connection->update(['property_url' => 'sc-domain:replacement.example']);

        return ['rows' => [['date' => '2026-10-06', 'clicks' => 10, 'impressions' => 100, 'position' => 8]], 'incomplete_from' => null];
    });
    (new SearchConsoleDailyHistory($client))->sync($this->connection);
    expect(SearchConsoleDailyMetric::count())->toBe(0);
});

test('scheduled reporting and wins jobs run once per server and do not replace existing reports', function (): void {
    $events = collect(app(Schedule::class)->events());
    foreach (['search-console:sync-daily', 'seo:detect-wins'] as $command) {
        $event = $events->first(fn ($event) => str_contains($event->command ?? '', $command));
        expect($event)->not->toBeNull()->and($event->onOneServer)->toBeTrue()->and($event->withoutOverlapping)->toBeTrue();
    }
    expect($events->contains(fn ($event) => str_contains($event->command ?? '', 'ranking-reports:dispatch-monthly')))->toBeTrue();
});
