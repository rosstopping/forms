<?php

use App\Ai\Agents\ContentRequestPixelWriter;
use App\Jobs\GenerateContentRequestPixelOptimisations;
use App\Jobs\StartContentGeneration;
use App\Models\ContentGeneration;
use App\Models\ContentPlan;
use App\Models\ContentRequest;
use App\Models\GithubUserAuthorization;
use App\Models\SeoImpact;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Services\ContentGenerationPromptGenerator;
use App\Services\ContentRepositoryPreflight;
use App\Services\ContentRequestPixelOptimisationGenerator;
use App\Services\ContentWorkSelector;
use App\Services\CopilotAgentClient;
use App\Services\GithubAppClient;
use App\Services\SearchConsoleClient;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Queue::fake();
    $this->travelTo(now()->setDate(2026, 10, 9)->setTime(9, 0));
    $this->admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    GithubUserAuthorization::factory()->for($this->admin)->create();
    $this->website = Website::factory()->create();
    $this->website->domains()->create(['domain' => 'example.com', 'is_primary' => true]);
    $this->repository = WebsiteRepository::factory()->for($this->website)->create();
    $this->plan = ContentPlan::factory()->for($this->website)->for($this->admin, 'creator')->create();
    $this->request = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Write an original guide.']);
    $this->actingAs($this->admin);
});

function queueControlUrl(Website $website, ContentRequest $request): string
{
    return route('admin.content-requests.queue.update', [$website, $request]);
}

test('manual holds preserve queue position and let independent work proceed', function (): void {
    $date = $this->request->created_at->toIso8601String();
    $this->request->update(['bumped_at' => now()]);
    $next = ContentRequest::factory()->for($this->website)->create();
    $this->patch(queueControlUrl($this->website, $this->request), ['action' => 'hold', 'hold_reason' => 'Await client facts.'])->assertRedirect();
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->make();
    expect(app(ContentWorkSelector::class)->select($generation)['requests']->modelKeys())->toBe([$next->id]);
    expect($this->request->fresh()->created_at->toIso8601String())->toBe($date);
    $this->get(route('admin.websites.section', [$this->website, 'content']))->assertSuccessful()->assertSee('On hold')->assertSee('Await client facts.')->assertSee('Release hold');
    Http::assertNothingSent();
    $this->patch(queueControlUrl($this->website, $this->request), ['action' => 'release'])->assertRedirect();
    expect(app(ContentWorkSelector::class)->select($generation)['requests']->modelKeys())->toBe([$this->request->id, $next->id]);
});

test('held work never starts Copilot or Pixel and cannot be manually taken', function (): void {
    $this->request->update(['held_at' => now(), 'hold_reason' => 'Wait for approval']);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'manual']);
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');
    $this->mock(SearchConsoleClient::class)->shouldNotReceive('performance');
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED)
        ->and($this->request->fresh()->picked_up_at)->toBeNull();
    $generator = $this->mock(ContentRequestPixelOptimisationGenerator::class);
    $generator->shouldNotReceive('generate');
    (new GenerateContentRequestPixelOptimisations($this->request, $this->admin))->handle($generator);
    $this->post(route('admin.content-requests.manual.take', [$this->website, $this->request]))->assertUnprocessable();
});

test('required integration pages use latest cooldown and become eligible at the full fourteen day boundary', function (): void {
    $this->request->update(['dependencies' => ['urls' => ['https://example.com/guides/'], 'files' => []]]);
    SeoImpact::factory()->for($this->website)->create(['target_urls' => ['https://example.com/guides'], 'live_at' => now()->subDays(2), 'status' => 'measuring']);
    $selector = app(ContentWorkSelector::class);
    $state = $selector->queueStates($this->plan)[$this->request->id];
    expect($state['state'])->toBe('cooldown')->and($state['eligible_at'])->toBe(now()->addDays(12)->toIso8601String());
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->make();
    expect($selector->select($generation)['requests'])->toBeEmpty();
    $this->travel(12)->days();
    expect($selector->queueStates($this->plan)[$this->request->id]['state'])->toBe('ready')
        ->and($selector->select($generation)['requests']->modelKeys())->toBe([$this->request->id]);
});

test('required repository files detect overlapping local reviews regardless of age', function (): void {
    $this->request->update(['dependencies' => ['files' => ['src/pages/guides/index.astro']]]);
    $previous = ContentGeneration::factory()->for($this->plan, 'plan')->create(['status' => ContentGeneration::STATUS_PULL_REQUEST_OPEN, 'pull_request_state' => 'open', 'started_at' => now()->subDays(30)]);
    ContentRequest::factory()->for($this->website)->for($previous, 'generation')->create(['picked_up_at' => now()->subDays(30), 'dependencies' => ['files' => ['src/pages/guides/index.astro']]]);
    expect(app(ContentWorkSelector::class)->queueStates($this->plan)[$this->request->id]['state'])->toBe('review');
    $previous->update(['pull_request_state' => 'closed']);
    expect(app(ContentWorkSelector::class)->queueStates($this->plan)[$this->request->id]['state'])->toBe('ready');
});

test('preflight skips a blocked request and checks again before a later attempt', function (): void {
    $this->request->update(['dependencies' => ['files' => ['public/sitemap.xml']]]);
    $next = ContentRequest::factory()->for($this->website)->create(['instructions' => 'Independent request']);
    $github = $this->mock(GithubAppClient::class);
    $github->shouldReceive('contentDependencyHistory')->once()->andReturn(['changes' => [['file' => 'public/sitemap.xml', 'changed_at' => now()->subDays(3)->toIso8601String()]], 'reviews' => []]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->make();
    $selector = app(ContentWorkSelector::class);
    expect($selector->select($generation, repositoryPreflight: true)['requests']->modelKeys())->toBe([$next->id]);
    expect($this->request->fresh()->preflight['state'])->toBe('cooldown')
        ->and($this->request->fresh()->preflight['eligible_at'])->toBe(now()->addDays(11)->toIso8601String())
        ->and($this->request->fresh()->picked_up_at)->toBeNull();
    $github->shouldReceive('contentDependencyHistory')->once()->andReturn(['changes' => [], 'reviews' => []]);
    expect($selector->select($generation, repositoryPreflight: true)['requests']->modelKeys())->toBe([$this->request->id, $next->id]);
    expect($this->request->fresh()->preflight['state'])->toBe('ready');
});

test('failed or review blocked preflight prevents paid submission', function (bool $failed): void {
    $this->request->update(['dependencies' => ['files' => ['public/sitemap.xml']]]);
    $github = $this->mock(GithubAppClient::class);
    $expectation = $github->shouldReceive('contentDependencyHistory')->once();
    if ($failed) {
        $expectation->andThrow(new RuntimeException('GitHub unavailable'));
    } else {
        $expectation->andReturn(['changes' => [], 'reviews' => ['#42']]);
    }
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->for($this->admin, 'requester')->create(['trigger' => 'manual']);
    $this->mock(CopilotAgentClient::class)->shouldNotReceive('startTask');
    app()->call([new StartContentGeneration($generation), 'handle']);
    expect($generation->fresh()->status)->toBe(ContentGeneration::STATUS_SKIPPED)
        ->and($this->request->fresh()->preflight['state'])->toBe($failed ? 'preflight' : 'review')
        ->and($this->request->fresh()->picked_up_at)->toBeNull();
})->with([true, false]);

test('queue controls validate scope dependencies and work reservations', function (): void {
    $url = queueControlUrl($this->website, $this->request);
    $this->patch($url, ['action' => 'hold'])->assertSessionHasErrors('hold_reason');
    $this->patch($url, ['action' => 'dependencies', 'urls' => 'https://elsewhere.test/guides'])->assertSessionHasErrors('urls.0');
    $this->patch($url, ['action' => 'dependencies', 'files' => '../secret'])->assertSessionHasErrors('files.0');
    $this->patch($url, ['action' => 'dependencies', 'files' => "public/sitemap.xml\npublic/sitemap.xml", 'urls' => 'https://example.com/guides'])->assertRedirect();
    expect($this->request->fresh()->dependencies)->toBe(['urls' => ['https://example.com/guides'], 'files' => ['public/sitemap.xml']]);
    $this->patch(queueControlUrl(Website::factory()->create(), $this->request), ['action' => 'hold', 'hold_reason' => 'Wrong site'])->assertNotFound();
    $this->request->update(['picked_up_at' => now()]);
    $this->patch($url, ['action' => 'hold', 'hold_reason' => 'Already picked up'])->assertUnprocessable();
});

test('queue controls respect viewer permissions and active Pixel reservations', function (): void {
    $viewer = User::factory()->create();
    $this->website->members()->attach($viewer, ['role' => 'viewer']);
    $url = queueControlUrl($this->website, $this->request);
    $this->actingAs($viewer)->patch($url, ['action' => 'hold', 'hold_reason' => 'Viewer'])->assertForbidden();
    $this->actingAs($this->admin);
    $lock = Cache::lock('content-request-work-'.$this->request->id, 180);
    $lock->get();
    try {
        $this->patch($url, ['action' => 'hold', 'hold_reason' => 'Concurrent Pixel'])->assertUnprocessable();
    } finally {
        $lock->release();
    }
    expect($this->request->fresh()->held_at)->toBeNull();
});

test('dependency brief reaches Copilot without expanding SEO measurement scope', function (): void {
    $this->request->update(['dependencies' => ['urls' => ['https://example.com/guides'], 'files' => ['public/sitemap.xml']]]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->create();
    $generation->setRelation('contentRequests', new Collection([$this->request]));
    expect(app(ContentGenerationPromptGenerator::class)->generate($generation))->toContain('Required integration dependencies', 'public/sitemap.xml');
    expect($this->request->seoImpact)->toBeNull();
});

test('GitHub dependency history checks direct commits merged reviews and renamed open files', function (): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    $github = Mockery::mock(GithubAppClient::class)->makePartial();
    $github->shouldReceive('installationToken')->once()->andReturn('test-token');
    $prefix = 'https://api.github.test/repos/'.$this->repository->full_name;
    Http::fake(function (Request $request) use ($prefix) {
        $url = parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($url, '/commits')) {
            expect($request['path'])->toBe('public/sitemap.xml')->and($request['sha'])->toBe($this->repository->default_branch);

            return Http::response([['commit' => ['committer' => ['date' => now()->subDays(7)->toIso8601String()]]]]);
        }
        if (str_ends_with($url, '/pulls')) {
            if ($request['state'] === 'closed') {
                return Http::response([['number' => 12, 'merged_at' => now()->subDay()->toIso8601String()]]);
            }

            return Http::response([['number' => 42]]);
        }
        if ($request->url() === $prefix.'/pulls/42/files?per_page=100') {
            return Http::response([['filename' => 'public/sitemap-new.xml', 'previous_filename' => 'public/sitemap.xml']]);
        }

        return Http::response([['filename' => 'public/sitemap.xml']]);
    });
    $result = $github->contentDependencyHistory($this->repository, ['public/sitemap.xml']);
    expect($result['reviews'])->toBe(['#42'])->and($result['changes'])->toHaveCount(2)
        ->and($result['changes'][1]['changed_at'])->toBe(now()->subDay()->toIso8601String());
    Http::assertSentCount(5);
});

test('GitHub incomplete open review evidence fails closed', function (string $case): void {
    config(['services.github.api_url' => 'https://api.github.test']);
    $github = Mockery::mock(GithubAppClient::class)->makePartial();
    $github->shouldReceive('installationToken')->once()->andReturn('test-token');
    Http::fake(function (Request $request) use ($case) {
        $url = parse_url($request->url(), PHP_URL_PATH);
        if (str_ends_with($url, '/commits')) {
            return Http::response([]);
        }
        if (str_ends_with($url, '/pulls')) {
            if ($request['state'] === 'closed') {
                return Http::response([]);
            }

            return Http::response($case === 'many_reviews' ? array_fill(0, 11, ['number' => 42]) : [['number' => 42]]);
        }

        return Http::response([['filename' => 'different.txt']], 200, ['Link' => '<https://api.github.test/next>; rel="next"']);
    });
    expect(fn () => $github->contentDependencyHistory($this->repository, ['public/sitemap.xml']))->toThrow(RuntimeException::class);
})->with(['many_reviews', 'paginated_files']);

test('repository merge dates restart the cooldown even when commit dates are older', function (): void {
    $this->request->update(['dependencies' => ['files' => ['public/sitemap.xml']]]);
    $this->mock(GithubAppClient::class)->shouldReceive('contentDependencyHistory')->once()->andReturn([
        'changes' => [['file' => 'public/sitemap.xml', 'changed_at' => now()->subDays(7)->toIso8601String()], ['file' => 'public/sitemap.xml', 'changed_at' => now()->subDay()->toIso8601String()]], 'reviews' => [],
    ]);
    expect(app(ContentRepositoryPreflight::class)->check($this->request, $this->repository)['eligible_at'])->toBe(now()->addDays(13)->toIso8601String());
});

test('ordinary managers preserve staff file dependencies and cannot change them', function (): void {
    $owner = $this->website->owner;
    $this->request->update(['dependencies' => ['urls' => [], 'files' => ['public/sitemap.xml']]]);
    $this->actingAs($owner)->patch(queueControlUrl($this->website, $this->request), ['action' => 'dependencies', 'urls' => 'https://example.com/guides'])->assertRedirect();
    expect($this->request->fresh()->dependencies['files'])->toBe(['public/sitemap.xml']);
    $this->patch(queueControlUrl($this->website, $this->request), ['action' => 'dependencies', 'files' => 'public/other.xml'])->assertSessionHasErrors('files');
});

test('preflight deadline leaves work unpicked without making a repository call', function (): void {
    $this->request->update(['dependencies' => ['files' => ['public/sitemap.xml']]]);
    $this->mock(GithubAppClient::class)->shouldNotReceive('contentDependencyHistory');
    expect(app(ContentRepositoryPreflight::class)->check($this->request, $this->repository, microtime(true) - 1)['state'])->toBe('preflight')
        ->and($this->request->fresh()->picked_up_at)->toBeNull();
});

test('only selected ready work is checked against GitHub', function (): void {
    $this->request->update(['dependencies' => ['files' => ['public/sitemap.xml']]]);
    ContentRequest::factory()->count(3)->for($this->website)->create(['dependencies' => ['files' => ['public/sitemap.xml']]]);
    $this->mock(GithubAppClient::class)->shouldReceive('contentDependencyHistory')->twice()->andReturn(['changes' => [], 'reviews' => []]);
    $generation = ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->make();
    expect(app(ContentWorkSelector::class)->select($generation, repositoryPreflight: true)['requests'])->toHaveCount(2);
});

test('queue controls cannot race a running content generation', function (): void {
    ContentGeneration::factory()->for($this->plan, 'plan')->for($this->repository, 'repository')->create(['status' => ContentGeneration::STATUS_RUNNING]);
    $this->patch(queueControlUrl($this->website, $this->request), ['action' => 'hold', 'hold_reason' => 'Too late'])->assertUnprocessable();
    expect($this->request->fresh()->held_at)->toBeNull();
});

test('overview explains an entirely held queue without issuing external requests', function (): void {
    $this->request->update(['held_at' => now(), 'hold_reason' => 'Wait for client facts']);
    $this->get(route('admin.overview'))->assertSuccessful()
        ->assertViewHas('contentQueue', fn ($rows): bool => $rows->sole()['state'] === 'Paused' && str_contains($rows->sole()['reason'], 'on hold'));
    Http::assertNothingSent();
});

test('held and dependent cooldown requests never prompt a Pixel writer', function (bool $held): void {
    config(['forms.pixel_ui_enabled' => true]);
    $this->website->update(['pixel_enabled' => true]);
    $this->request->update(['held_at' => $held ? now() : null, 'dependencies' => ['urls' => ['https://example.com/guides']]]);
    SeoImpact::factory()->for($this->website)->create(['target_urls' => ['https://example.com/guides'], 'live_at' => now()->subDay()]);
    ContentRequestPixelWriter::fake()->preventStrayPrompts();
    expect(app(ContentRequestPixelOptimisationGenerator::class)->generate($this->request, $this->admin))->toBe(0);
    ContentRequestPixelWriter::assertNeverPrompted();
    expect($this->request->fresh()->pixel_processed_at)->toBeNull();
})->with([true, false]);
