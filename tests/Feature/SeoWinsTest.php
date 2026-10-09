<?php

use App\Jobs\CheckSeoTargetKeywordRanking;
use App\Jobs\DetectSeoWins;
use App\Models\SeoTargetKeyword;
use App\Models\SeoTargetKeywordRanking;
use App\Models\SeoWin;
use App\Models\User;
use App\Models\Website;
use App\Services\SeoTargetKeywordRankChecker;
use App\Services\SeoWinDetector;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(12, 0));
    Http::preventStrayRequests();
    Queue::fake();
    Mail::fake();
    $this->website = Website::factory()->create();
    $this->target = SeoTargetKeyword::factory()->for($this->website)->create(['term' => 'event planning', 'priority' => 'high', 'intended_url' => 'https://example.com/events']);
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
});

function winRanking(SeoTargetKeyword $target, ?int $position, int $daysAgo, array $attributes = []): SeoTargetKeywordRanking
{
    return SeoTargetKeywordRanking::factory()->create(['seo_target_keyword_id' => $target->id, 'website_id' => $target->website_id,
        'position' => $position, 'status' => $position === null ? 'not_found' : 'ranked', 'observed_at' => now()->subDays($daysAgo),
        'location_code' => config('services.dataforseo.location_code'), 'language_code' => config('services.dataforseo.language_code'), ...$attributes]);
}

test('meaningful ranking wins require persistence and keep immutable deduplicated evidence', function (array $positions, string $rule): void {
    winRanking($this->target, $positions[0], 14);
    winRanking($this->target, $positions[1], 7);
    winRanking($this->target, $positions[2], 0);
    $detector = app(SeoWinDetector::class);
    $detector->detect($this->website);
    $win = SeoWin::sole();
    expect($win->rule)->toBe($rule)->and($win->evidence['observations'])->toHaveCount(3)
        ->and($win->evidence['intended_url'])->toBe('https://example.com/events')->and($win->evidence['traffic_impact'])->toBeNull()
        ->and($win->importance)->toBe('high')->and($win->approved_at)->toBeNull();
    $original = $win->evidence;
    $win->update(['dismissed_at' => now()]);
    $detector->detect($this->website);
    expect(SeoWin::count())->toBe(1)->and($win->fresh()->evidence)->toBe($original);
    Http::assertNothingSent();
    Mail::assertNothingSent();
})->with([
    [[14, 9, 8], 'top_10'],
    [[6, 3, 2], 'top_3'],
    [[null, 30, 25], 'newly_ranked'],
    [[14, 3, 2], 'top_3'],
]);

test('single fluctuations and insufficient observations do not become wins', function (array $positions): void {
    foreach ($positions as $index => $position) {
        winRanking($this->target, $position, (count($positions) - $index - 1) * 7);
    }
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::where('category', 'win')->count())->toBe(0);
})->with([[[12, 11, 12]], [[12, 9, 11]], [[12, 9]], [[null, 70, 65]], [[8, 7, 6]]]);

test('cached duplicate observations cannot confirm a milestone', function (): void {
    winRanking($this->target, 14, 14);
    $ranking = winRanking($this->target, 9, 7);
    for ($index = 0; $index < 5; $index++) {
        winRanking($this->target, 9, 7, ['cached' => true, 'observed_at' => $ranking->observed_at]);
    }
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
    winRanking($this->target, 8, 0);
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::count())->toBe(1);
});

test('failures stale baselines short intervals and mismatched markets block confirmation', function (array $latest, int $baselineDays, int $firstDays, int $latestDays): void {
    winRanking($this->target, 14, $baselineDays);
    winRanking($this->target, 9, $firstDays);
    winRanking($this->target, 8, $latestDays, $latest);
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
})->with([
    [['status' => 'failed'], 14, 7, 0],
    [['location_code' => 2840], 14, 7, 0],
    [['language_code' => 'fr'], 14, 7, 0],
    [['device' => 'mobile'], 14, 7, 0],
    [['provider' => 'other'], 14, 7, 0],
    [[], 100, 7, 0],
    [[], 14, 1, 0],
    [[], 50, 35, 30],
    [[], 50, 30, 0],
    [[], 14, 7, -1],
]);

test('personal best wins use all recorded history and require a material improvement', function (): void {
    foreach ([[50, 35], [48, 28], [46, 21], [42, 7], [41, 0]] as [$position, $days]) {
        winRanking($this->target, $position, $days);
    }
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::sole()->rule)->toBe('personal_best')->and(SeoWin::sole()->evidence['historical_best_before'])->toBe(46);
    SeoWin::query()->delete();
    winRanking($this->target, 20, 500);
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
});

test('inactive websites and archived targets are not considered', function (): void {
    winRanking($this->target, 14, 14);
    winRanking($this->target, 9, 7);
    winRanking($this->target, 8, 0);
    $this->target->update(['archived_at' => now()]);
    app(SeoWinDetector::class)->detect($this->website);
    $this->target->update(['archived_at' => null]);
    $this->website->update(['is_active' => false]);
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
});

test('the inbox scopes sites status and pagination without paid or AI work', function (): void {
    SeoWin::factory()->count(16)->for($this->website)->create();
    SeoWin::factory()->create(['shared_at' => now()]);
    SeoWin::factory()->create(['dismissed_at' => now()]);
    $this->actingAs($this->admin)->get(route('admin.overview', ['hub' => 'wins', 'site_id' => $this->website->id, 'wins_page' => 2]))
        ->assertSuccessful()->assertSee('Sitewell Wins')->assertSee('Approve update')
        ->assertViewHas('wins', fn ($wins) => $wins->total() === 16 && $wins->count() === 1);
    $this->get(route('admin.overview', ['hub' => 'wins', 'win_status' => 'shared']))->assertSuccessful()->assertViewHas('wins', fn ($wins) => $wins->total() === 1);
    Http::assertNothingSent();
    Queue::assertNothingPushed();
    Mail::assertNothingSent();
});

test('client updates must be explicitly approved before sharing and edits clear approval', function (): void {
    $win = SeoWin::factory()->for($this->website)->create();
    $this->actingAs($this->admin);
    $route = route('admin.seo-wins.update', $win);
    $this->patch($route, ['action' => 'share'])->assertSessionHasErrors('action');
    $this->patch($route, ['action' => 'approve', 'client_draft' => 'A friendly reviewed update'])->assertRedirect();
    expect($win->fresh()->approved_by)->toBe($this->admin->id);
    $this->get(route('admin.overview', ['hub' => 'wins']))->assertSuccessful()->assertSee('Copy approved text');
    expect($win->fresh()->shared_at)->toBeNull();
    $this->patch($route, ['action' => 'save', 'client_draft' => 'Edited update'])->assertRedirect();
    expect($win->fresh()->approved_at)->toBeNull();
    $this->patch($route, ['action' => 'share'])->assertSessionHasErrors('action');
    $this->patch($route, ['action' => 'approve', 'client_draft' => 'Final reviewed update'])->assertRedirect();
    $this->patch($route, ['action' => 'share', 'client_draft' => 'Unapproved injected text'])->assertRedirect();
    expect($win->fresh()->shared_text)->toBe('Final reviewed update')->and($win->fresh()->shared_by)->toBe($this->admin->id);
    $this->patch($route, ['action' => 'save', 'client_draft' => 'Overwrite'])->assertSessionHasErrors('action');
    expect($win->fresh()->shared_text)->toBe('Final reviewed update');
    Mail::assertNothingSent();
    Queue::assertNothingPushed();
});

test('website customers cannot approve or share client updates', function (): void {
    $win = SeoWin::factory()->for($this->website)->create();
    $this->actingAs($this->website->owner)->get(route('admin.overview', ['hub' => 'wins']))->assertForbidden();
    $this->patch(route('admin.seo-wins.update', $win), ['action' => 'approve', 'client_draft' => 'No'])->assertForbidden();

});

test('scheduled detection only queues eligible websites and cannot buy ranking checks', function (): void {
    Website::factory()->create(['is_active' => false])->seoTargetKeywords()->create(['term' => 'inactive']);
    $this->artisan('seo:detect-wins')->assertSuccessful();
    Queue::assertPushed(DetectSeoWins::class, 1);
    Http::assertNothingSent();
});

test('a repeated provider task is not independent confirmation even if timestamps differ', function (): void {
    winRanking($this->target, 14, 14);
    winRanking($this->target, 9, 7, ['provider_task_id' => 'same-task']);
    winRanking($this->target, 8, 0, ['provider_task_id' => 'same-task']);
    app(SeoWinDetector::class)->detect($this->website);
    expect(SeoWin::count())->toBe(0);
});

test('existing ranking jobs trigger saved evidence detection and skip archived or deleted targets', function (): void {
    $checker = Mockery::mock(SeoTargetKeywordRankChecker::class);
    $checker->shouldReceive('check')->once()->andReturn(winRanking($this->target, 8, 0));
    (new CheckSeoTargetKeywordRanking($this->target))->handle($checker);
    Queue::assertPushed(DetectSeoWins::class, fn ($job) => $job->website->is($this->website));
    $this->target->update(['archived_at' => now()]);
    (new CheckSeoTargetKeywordRanking($this->target))->handle($checker);
    $this->target->delete();
    (new CheckSeoTargetKeywordRanking($this->target))->handle($checker);
    Queue::assertPushed(DetectSeoWins::class, 1);
});
