<?php

use App\Jobs\BuildWordPressStaticRelease;
use App\Models\GithubInstallation;
use App\Models\User;
use App\Models\Website;
use App\Models\WebsiteRepository;
use App\Models\WordpressConnection;
use App\Models\WordpressStaticRelease;
use App\Services\GithubAppClient;
use App\Services\WordPressDeploymentNotifier;
use App\Services\WordPressStaticReleaseBuilder;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\mock;

/** @param array<string, string> $files */
function githubArchive(array $files): string
{
    $path = tempnam(sys_get_temp_dir(), 'sitewell-release-');
    $archive = new ZipArchive;
    $archive->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $archive->addEmptyDir('repository-root');

    foreach ($files as $name => $contents) {
        $archive->addFromString('repository-root/'.$name, $contents);
    }

    $archive->close();
    $contents = file_get_contents($path);
    unlink($path);

    return $contents;
}

/** @return array{User, Website, WebsiteRepository, WordpressConnection, string} */
function connectedWordpressReleaseWebsite(): array
{
    $owner = User::factory()->create();
    $website = Website::factory()->for($owner, 'owner')->create();
    $installation = GithubInstallation::factory()->for($owner, 'installer')->create();
    $repository = WebsiteRepository::factory()->for($website)->for($installation, 'installation')->create();
    $credential = 'swp_'.str_repeat('a', 64);
    $connection = WordpressConnection::factory()->for($website)->create([
        'credential_hash' => hash('sha256', $credential),
        'wordpress_url' => 'https://example.com',
    ]);

    return [$owner, $website, $repository, $connection, $credential];
}

it('queues a static release for a manageable connected website', function (): void {
    Queue::fake();
    [$owner, $website] = connectedWordpressReleaseWebsite();

    $this->actingAs($owner)
        ->post(route('admin.websites.wordpress.releases.store', $website))
        ->assertRedirect(route('admin.websites.show', ['website' => $website, 'tab' => 'wordpress']))
        ->assertSessionHas('status');

    $release = $website->wordpressStaticReleases()->sole();
    expect($release->status)->toBe(WordpressStaticRelease::STATUS_QUEUED)
        ->and($release->source_ref)->toBe($website->repository->default_branch);
    Queue::assertPushed(BuildWordPressStaticRelease::class, fn (BuildWordPressStaticRelease $job): bool => $job->releaseId === $release->id);
});

it('packages the configured repository path as a flat verified static release', function (): void {
    Storage::fake('local');
    [$owner, $website, $repository] = connectedWordpressReleaseWebsite();
    $repository->update(['project_path' => 'dist']);
    $release = WordpressStaticRelease::factory()->for($website)->for($owner, 'creator')->create([
        'status' => WordpressStaticRelease::STATUS_QUEUED,
        'commit_sha' => null,
        'source_ref' => 'main',
        'storage_path' => null,
        'checksum' => null,
        'size' => null,
        'ready_at' => null,
    ]);
    $sourceArchive = githubArchive([
        'README.md' => 'Ignored',
        'dist/index.html' => '<h1>Live website</h1>',
        'dist/assets/site.css' => 'body { color: navy; }',
        'dist/.htaccess' => 'Deny from all',
        'dist/assets/.DS_Store' => 'Ignored metadata',
        'dist/config.yml' => 'secret: ignored',
        'dist/backdoor.php' => '<?php echo "unsafe";',
    ]);
    mock(GithubAppClient::class)
        ->shouldReceive('repositoryArchive')
        ->once()
        ->withArgs(fn (WebsiteRepository $selected, string $sourceRef): bool => $selected->is($repository) && $sourceRef === 'main')
        ->andReturn(['commit_sha' => str_repeat('b', 40), 'archive' => $sourceArchive]);

    $built = app(WordPressStaticReleaseBuilder::class)->build($release, $repository);

    expect($built->status)->toBe(WordpressStaticRelease::STATUS_READY)
        ->and($built->commit_sha)->toBe(str_repeat('b', 40))
        ->and($built->checksum)->toBe(hash_file('sha256', Storage::disk('local')->path($built->storage_path)))
        ->and($built->size)->toBeGreaterThan(0);

    $packaged = new ZipArchive;
    $packaged->open(Storage::disk('local')->path($built->storage_path));
    expect($packaged->getFromName('index.html'))->toBe('<h1>Live website</h1>')
        ->and($packaged->getFromName('assets/site.css'))->toBe('body { color: navy; }')
        ->and($packaged->locateName('README.md'))->toBeFalse()
        ->and($packaged->locateName('.htaccess'))->toBeFalse()
        ->and($packaged->locateName('assets/.DS_Store'))->toBeFalse()
        ->and($packaged->locateName('config.yml'))->toBeFalse()
        ->and($packaged->locateName('backdoor.php'))->toBeFalse();
    $packaged->close();
});

it('exposes and records a ready release only for its authenticated plugin', function (): void {
    Storage::fake('local');
    [$owner, $website, $repository, $connection, $credential] = connectedWordpressReleaseWebsite();
    $archive = githubArchive(['index.html' => '<h1>Release</h1>']);
    $path = 'wordpress-releases/'.$website->id.'/release.zip';
    Storage::disk('local')->put($path, $archive);
    $release = WordpressStaticRelease::factory()->for($website)->for($owner, 'creator')->create([
        'commit_sha' => str_repeat('c', 40),
        'storage_path' => $path,
        'checksum' => hash('sha256', $archive),
        'size' => strlen($archive),
    ]);

    $this->withToken('wrong')->getJson(route('wordpress-connections.releases.current', $connection->public_id))->assertUnauthorized();

    $manifest = $this->withToken($credential)
        ->getJson(route('wordpress-connections.releases.current', $connection->public_id))
        ->assertSuccessful()
        ->assertJsonPath('data.release_id', $release->public_id)
        ->assertJsonPath('data.checksum', $release->checksum);

    $this->withToken($credential)
        ->get($manifest->json('data.download_url'))
        ->assertSuccessful()
        ->assertHeader('cache-control', 'no-store, private');

    $this->withToken($credential)
        ->postJson(route('wordpress-connections.releases.activated', [$connection->public_id, $release->public_id]))
        ->assertNoContent();

    expect($connection->fresh()->active_release_public_id)->toBe($release->public_id)
        ->and($connection->fresh()->last_deployed_at)->not->toBeNull()
        ->and($release->fresh()->activated_at)->not->toBeNull();

    $this->withToken($credential)
        ->getJson(route('wordpress-connections.releases.current', [
            'connectionId' => $connection->public_id,
            'active_release' => $release->public_id,
        ]))
        ->assertNoContent();
});

it('queues an immediate release when GitHub pushes the connected branch', function (): void {
    Queue::fake();
    config(['services.github.webhook_secret' => 'webhook-secret']);
    [$owner, $website, $repository] = connectedWordpressReleaseWebsite();
    $payload = json_encode([
        'ref' => 'refs/heads/'.$repository->default_branch,
        'after' => str_repeat('d', 40),
        'repository' => ['id' => $repository->repository_id],
    ], JSON_THROW_ON_ERROR);

    $this->call('POST', route('github.webhook'), [], [], [], [
        'CONTENT_TYPE' => 'application/json',
        'HTTP_X_GITHUB_EVENT' => 'push',
        'HTTP_X_HUB_SIGNATURE_256' => 'sha256='.hash_hmac('sha256', $payload, 'webhook-secret'),
    ], $payload)->assertSuccessful();

    $release = $website->wordpressStaticReleases()->sole();
    expect($release->commit_sha)->toBe(str_repeat('d', 40))
        ->and($release->status)->toBe(WordpressStaticRelease::STATUS_QUEUED);
    Queue::assertPushed(BuildWordPressStaticRelease::class, fn (BuildWordPressStaticRelease $job): bool => $job->releaseId === $release->id);
});

it('notifies the connected WordPress site when a release is ready', function (): void {
    Http::preventStrayRequests();
    Http::fake(['93.184.216.34/wp-json/sitewell-static-frontend/v1/deploy' => Http::response(['installed' => true])]);
    [$owner, $website, $repository, $connection] = connectedWordpressReleaseWebsite();
    $connection->update(['wordpress_url' => 'https://93.184.216.34']);
    $release = WordpressStaticRelease::factory()->for($website)->for($owner, 'creator')->create();

    expect(app(WordPressDeploymentNotifier::class)->notify($connection, $release))->toBeTrue();

    Http::assertSent(fn (Request $request): bool => $request->url() === 'https://93.184.216.34/wp-json/sitewell-static-frontend/v1/deploy'
        && $request->hasHeader('Authorization', 'Bearer '.$connection->webhook_secret)
        && $request['release_id'] === $release->public_id);
});
