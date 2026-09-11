<?php

use App\Jobs\BuildWordPressStaticRelease;
use App\Models\User;
use App\Models\WebsiteRepository;
use App\Models\WordpressConnection;
use App\Models\WordpressStaticRelease;
use App\Services\GithubAppClient;
use App\Services\WordPressDeploymentNotifier;
use App\Services\WordPressStaticReleaseBuilder;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

beforeEach(function (): void {
    Http::preventStrayRequests();
    Storage::fake('local');
    config(['services.github.api_url' => 'https://api.github.test', 'services.github.webhook_secret' => 'webhook-secret']);
    $this->repository = WebsiteRepository::factory()->create([
        'project_path' => 'apps/website',
        'wordpress_workflow_path' => '.github/workflows/build-site.yml',
        'wordpress_artifact_name' => 'wordpress-site',
    ]);
    $this->connection = WordpressConnection::factory()->for($this->repository->website)->create();
    $this->sha = str_repeat('a', 40);
    $this->run = [
        'id' => 123,
        'status' => 'completed',
        'conclusion' => 'success',
        'event' => 'push',
        'head_sha' => $this->sha,
        'head_branch' => 'main',
        'path' => '.github/workflows/build-site.yml',
        'repository' => ['id' => $this->repository->repository_id],
        'head_repository' => ['id' => $this->repository->repository_id],
    ];
    mock(GithubAppClient::class)->makePartial()->shouldReceive('installationToken')->andReturn('installation-secret');
});

/** @param array<string, string> $files */
function actionsDeploymentZip(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'actions-artifact-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    foreach ($files as $name => $contents) {
        $zip->addFromString($name, $contents);
    }
    $zip->close();
    $contents = file_get_contents($path);
    unlink($path);

    return $contents;
}

/** @param array<string, mixed> $payload */
function sendActionsDeploymentWebhook(array $payload, string $event = 'workflow_run'): void
{
    $body = json_encode($payload, JSON_THROW_ON_ERROR);
    test()->call('POST', route('github.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => $event,
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $body, 'webhook-secret'),
    ], $body)->assertSuccessful();
}

/** @param array<string, mixed> $artifactOverrides */
function fakeActionsDeploymentDownload(array $artifactOverrides = [], ?string $zip = null, string $downloadUrl = 'https://production.blob.core.windows.net/artifact?signature=test'): void
{
    $zip ??= actionsDeploymentZip(['index.html' => '<h1>Built Eleventy site</h1>', 'assets/app.css' => 'body{}', 'backdoor.php' => '<?php evil();']);
    $base = 'https://api.github.test/repos/'.test()->repository->full_name;
    Http::fake([
        $base.'/commits/main' => Http::response(['sha' => test()->sha]),
        $base.'/actions/workflows/build-site.yml/runs*' => Http::response(['workflow_runs' => [['id' => 123]]]),
        $base.'/actions/runs/123' => Http::response(test()->run),
        $base.'/actions/runs/123/artifacts*' => Http::response(['total_count' => 1, 'artifacts' => [array_merge([
            'id' => 456, 'name' => 'wordpress-site', 'expired' => false, 'size_in_bytes' => strlen($zip),
            'digest' => 'sha256:'.hash('sha256', $zip),
        ], $artifactOverrides)]]),
        $base.'/actions/artifacts/456/zip' => Http::response('', 302, ['Location' => $downloadUrl]),
        'https://production.blob.core.windows.net/*' => Http::response($zip),
    ]);
}

it('waits for a completed build instead of deploying a source push', function (): void {
    Queue::fake();
    sendActionsDeploymentWebhook([
        'ref' => 'refs/heads/main', 'after' => $this->sha,
        'repository' => ['id' => $this->repository->repository_id],
    ], 'push');
    Queue::assertNothingPushed();
    expect($this->repository->website->wordpressStaticReleases()->count())->toBe(0);
});

it('queues a successful configured build only once', function (): void {
    Queue::fake();
    $payload = [
        'action' => 'completed', 'workflow_run' => $this->run,
        'repository' => ['id' => $this->repository->repository_id],
        'installation' => ['id' => $this->repository->installation->installation_id],
    ];
    sendActionsDeploymentWebhook($payload);
    sendActionsDeploymentWebhook($payload);
    Queue::assertPushed(BuildWordPressStaticRelease::class, 1);
    expect($this->repository->website->wordpressStaticReleases()->sole())
        ->commit_sha->toBe($this->sha)
        ->github_workflow_run_id->toBe(123);
});

it('ignores unsuitable build events', function (string $field, mixed $value): void {
    Queue::fake();
    data_set($this->run, $field, $value);
    sendActionsDeploymentWebhook([
        'action' => 'completed', 'workflow_run' => $this->run,
        'repository' => ['id' => $this->repository->repository_id],
        'installation' => ['id' => $this->repository->installation->installation_id],
    ]);
    Queue::assertNothingPushed();
})->with([
    'failed' => ['conclusion', 'failure'],
    'cancelled' => ['conclusion', 'cancelled'],
    'pull request' => ['event', 'pull_request'],
    'wrong branch' => ['head_branch', 'feature'],
    'wrong workflow' => ['path', '.github/workflows/test.yml'],
    'fork' => ['head_repository.id', 0],
    'invalid SHA' => ['head_sha', 'invalid'],
]);

it('packages a verified artifact at its root and not the source project path', function (): void {
    $files = [
        'index.html' => '<h1>Built Eleventy site</h1>',
        'assets/app.css' => 'body{}',
        'sitemap.xml' => '<?xml version="1.0"?><urlset><url><loc>https://rowglo.co.uk/plumbing/</loc></url></urlset>',
        'robots.txt' => "User-agent: *\nSitemap: https://rowglo.co.uk/sitemap.xml\n",
        'backdoor.php' => '<?php evil();',
    ];
    foreach (['plumbing', 'heating', 'boilers', 'bathrooms', 'drainage', 'repairs', 'emergencies'] as $service) {
        $files[$service.'/index.html'] = '<html><body><section class="hero"><h1>'.ucfirst($service).'</h1></section></body></html>';
    }
    fakeActionsDeploymentDownload(zip: actionsDeploymentZip($files));
    $release = WordpressStaticRelease::factory()->for($this->repository->website)->create([
        'status' => 'queued', 'commit_sha' => $this->sha, 'github_workflow_run_id' => 123,
    ]);
    $notifier = mock(WordPressDeploymentNotifier::class);
    $notifier->shouldReceive('notify')->once()->andReturn(true);
    (new BuildWordPressStaticRelease($release->id))->handle(app(WordPressStaticReleaseBuilder::class), $notifier);
    $release->refresh();
    expect($release->status)->toBe('ready')->and($release->github_workflow_run_id)->toBe(123);
    $zip = new ZipArchive;
    $zip->open(Storage::disk('local')->path($release->storage_path));
    expect($zip->getFromName('index.html'))->toBe('<h1>Built Eleventy site</h1>')
        ->and($zip->getFromName('assets/app.css'))->toBe('body{}')
        ->and($zip->locateName('backdoor.php'))->toBeFalse();
    foreach ($files as $path => $contents) {
        if ($path !== 'backdoor.php') {
            expect($zip->getFromName($path))->toBe($contents);
        }
    }
    $zip->close();
    Http::assertSent(fn ($request): bool => str_starts_with($request->url(), 'https://production.blob.core.windows.net/') && ! $request->hasHeader('Authorization'));
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/zipball/'));
});

it('finds the latest commit build for a manual release', function (): void {
    fakeActionsDeploymentDownload();
    $archive = app(GithubAppClient::class)->workflowArtifactArchive($this->repository);
    expect($archive['workflow_run_id'])->toBe(123)->and($archive['commit_sha'])->toBe($this->sha);
    Http::assertSent(fn ($request): bool => str_contains($request->url(), '/workflows/build-site.yml/runs') && $request['head_sha'] === $this->sha);
});

it('revalidates workflow metadata through GitHub before downloading', function (string $field, mixed $value): void {
    data_set($this->run, $field, $value);
    fakeActionsDeploymentDownload();
    expect(fn () => app(GithubAppClient::class)->workflowArtifactArchive($this->repository, $this->sha, 123))
        ->toThrow(RuntimeException::class, 'requires a successful build');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), '/artifacts'));
})->with([
    'running' => ['status', 'in_progress'],
    'failed' => ['conclusion', 'failure'],
    'different commit' => ['head_sha', str_repeat('b', 40)],
    'pull request' => ['event', 'pull_request'],
    'different repository' => ['repository.id', 0],
    'fork' => ['head_repository.id', 0],
    'different workflow' => ['path', '.github/workflows/untrusted.yml'],
]);

it('rejects superseded builds before downloading', function (): void {
    fakeActionsDeploymentDownload();
    expect(fn () => app(GithubAppClient::class)->workflowArtifactArchive($this->repository, str_repeat('b', 40), 123))
        ->toThrow(RuntimeException::class, 'superseded');
});

it('leaves the live release available when the artifact is unusable', function (array $overrides): void {
    $live = WordpressStaticRelease::factory()->for($this->repository->website)->create();
    fakeActionsDeploymentDownload($overrides);
    $release = WordpressStaticRelease::factory()->for($this->repository->website)->create([
        'status' => 'queued', 'commit_sha' => $this->sha, 'github_workflow_run_id' => 123,
    ]);
    $notifier = mock(WordPressDeploymentNotifier::class);
    $notifier->shouldNotReceive('notify');
    expect(fn () => (new BuildWordPressStaticRelease($release->id))->handle(app(WordPressStaticReleaseBuilder::class), $notifier))
        ->toThrow(RuntimeException::class);
    expect($release->fresh()->status)->toBe('failed')->and($live->fresh()->status)->toBe('ready');
})->with([
    'missing artifact name' => [['name' => 'wrong-artifact']],
    'expired' => [['expired' => true]],
    'oversized' => [['size_in_bytes' => 60 * 1024 * 1024]],
    'wrong checksum' => [['digest' => 'sha256:invalid']],
]);

it('rejects unsafe or incorrectly nested artifact files', function (array $files): void {
    fakeActionsDeploymentDownload(zip: actionsDeploymentZip($files));
    $release = WordpressStaticRelease::factory()->for($this->repository->website)->create([
        'status' => 'queued', 'commit_sha' => $this->sha, 'github_workflow_run_id' => 123,
    ]);
    expect(fn () => app(WordPressStaticReleaseBuilder::class)->build($release, $this->repository))->toThrow(RuntimeException::class);
    expect(Storage::disk('local')->allFiles())->toBe([]);
})->with([
    'traversal' => [['index.html' => 'home', '../outside.html' => 'bad']],
    'nested output' => [['_site/index.html' => 'home']],
    'unbuilt artifact' => [['index.html' => 'home', 'plumbing/index.html' => "---\nhero:\n  title: Plumbing\n---\n{% include 'hero.njk' %}"]],
]);

it('never downloads source with incomplete artifact settings', function (string $missing): void {
    $this->repository->update([$missing => null]);
    $release = WordpressStaticRelease::factory()->for($this->repository->website)->create(['status' => 'queued']);

    expect(fn () => app(WordPressStaticReleaseBuilder::class)->build($release, $this->repository))
        ->toThrow(RuntimeException::class, 'Configure both');
    Http::assertNothingSent();
})->with(['wordpress_workflow_path', 'wordpress_artifact_name']);

it('saves and displays separate source and artifact settings', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    mock(GithubAppClient::class)->shouldReceive('repositories')->andReturn([[
        'id' => $this->repository->repository_id, 'full_name' => $this->repository->full_name,
        'default_branch' => 'main', 'private' => true,
    ]]);
    $this->actingAs($admin)->post(route('admin.website-repositories.store', $this->repository->website), [
        'repository' => $this->repository->github_installation_id.':'.$this->repository->repository_id,
        'project_path' => 'src/site',
        'wordpress_workflow_path' => '.github/workflows/build-site.yml',
        'wordpress_artifact_name' => 'wordpress-site',
    ])->assertSessionHasNoErrors();
    expect($this->repository->fresh())->project_path->toBe('src/site')->wordpress_artifact_name->toBe('wordpress-site');
    $this->get(route('admin.website-repositories.create', $this->repository->website))
        ->assertSuccessful()->assertSee('value="src/site"', false)
        ->assertSee('value=".github/workflows/build-site.yml"', false)
        ->assertSee('value="wordpress-site"', false);
});

it('requires a workflow and artifact together', function (): void {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $this->actingAs($admin)->post(route('admin.website-repositories.store', $this->repository->website), [
        'repository' => $this->repository->github_installation_id.':'.$this->repository->repository_id,
        'wordpress_artifact_name' => 'wordpress-site',
    ])->assertSessionHasErrors('wordpress_workflow_path');
});

it('rejects a build if a newer commit arrives during the download', function (): void {
    $archive = actionsDeploymentZip(['index.html' => 'old build']);
    $client = mock(GithubAppClient::class);
    $client->shouldReceive('workflowArtifactArchive')->once()->andReturn([
        'archive' => $archive, 'commit_sha' => $this->sha, 'workflow_run_id' => 123,
    ]);
    $client->shouldReceive('branchCommit')->once()->andReturn(str_repeat('b', 40));
    $release = WordpressStaticRelease::factory()->for($this->repository->website)->create([
        'status' => 'queued', 'commit_sha' => $this->sha, 'github_workflow_run_id' => 123,
    ]);
    expect(fn () => app(WordPressStaticReleaseBuilder::class)->build($release, $this->repository))
        ->toThrow(RuntimeException::class, 'newer commit arrived');
    expect(Storage::disk('local')->allFiles())->toBe([]);
});

it('does not follow an artifact redirect to an unexpected host', function (): void {
    fakeActionsDeploymentDownload(downloadUrl: 'https://attacker.example/steal');
    expect(fn () => app(GithubAppClient::class)->workflowArtifactArchive($this->repository, $this->sha, 123))
        ->toThrow(RuntimeException::class, 'unexpected artifact download location');
    Http::assertNotSent(fn ($request): bool => str_contains($request->url(), 'attacker.example'));
});

it('does not queue a build from a different app installation', function (): void {
    Queue::fake();
    sendActionsDeploymentWebhook([
        'action' => 'completed', 'workflow_run' => $this->run,
        'repository' => ['id' => $this->repository->repository_id],
        'installation' => ['id' => 0],
    ]);
    Queue::assertNothingPushed();
});
