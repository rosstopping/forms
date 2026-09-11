<?php

namespace App\Services;

use App\Models\WebsiteRepository;
use App\Models\WordpressStaticRelease;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;
use ZipArchive;

class WordPressStaticReleaseBuilder
{
    private const MAX_ARCHIVE_BYTES = 50 * 1024 * 1024;

    private const MAX_EXTRACTED_BYTES = 150 * 1024 * 1024;

    private const MAX_FILES = 10000;

    private const ALLOWED_EXTENSIONS = [
        'avif', 'css', 'gif', 'htm', 'html', 'ico', 'jpeg', 'jpg', 'js', 'json', 'map',
        'mp3', 'mp4', 'ogg', 'pdf', 'png', 'svg', 'ttf', 'txt', 'webm', 'webmanifest',
        'webp', 'woff', 'woff2', 'xml',
    ];

    public function __construct(private GithubAppClient $github) {}

    public function build(WordpressStaticRelease $release, WebsiteRepository $repository): WordpressStaticRelease
    {
        $release->update(['status' => WordpressStaticRelease::STATUS_BUILDING, 'error' => null]);
        $fromArtifact = $repository->usesActionsArtifact();

        if (! $fromArtifact && (filled($repository->wordpress_workflow_path) || filled($repository->wordpress_artifact_name))) {
            throw new RuntimeException('Configure both the WordPress workflow path and artifact name. Incomplete build settings cannot deploy repository source.');
        }

        if ($release->github_workflow_run_id !== null && ! $fromArtifact) {
            throw new RuntimeException('GitHub Actions deployment was disabled after this build was queued.');
        }

        $archive = $fromArtifact
            ? $this->github->workflowArtifactArchive($repository, $release->commit_sha, $release->github_workflow_run_id)
            : $this->github->repositoryArchive($repository, $release->commit_sha ?: $release->source_ref);

        if (strlen($archive['archive']) > self::MAX_ARCHIVE_BYTES) {
            throw new RuntimeException('The GitHub repository archive is larger than the 50 MB deployment limit.');
        }

        $directory = 'wordpress-releases/'.$release->website_id;
        $sourcePath = "{$directory}/{$release->public_id}.source.zip";
        $releasePath = "{$directory}/{$release->public_id}.zip";
        $disk = Storage::disk('local');
        $disk->makeDirectory($directory);
        $disk->put($sourcePath, $archive['archive']);

        try {
            $this->repack($disk->path($sourcePath), $disk->path($releasePath), $fromArtifact ? null : $repository->project_path, ! $fromArtifact);

            if ($fromArtifact && $this->github->branchCommit($repository) !== $archive['commit_sha']) {
                throw new RuntimeException('A newer commit arrived while preparing this release. Waiting for its build.');
            }
        } catch (Throwable $exception) {
            $disk->delete($releasePath);

            throw $exception;
        } finally {
            $disk->delete($sourcePath);
        }

        $checksum = hash_file('sha256', $disk->path($releasePath));
        $size = $disk->size($releasePath);

        if (! is_string($checksum)) {
            $disk->delete($releasePath);

            throw new RuntimeException('Sitewell could not checksum the static release.');
        }

        $release->update([
            'commit_sha' => strtolower($archive['commit_sha']),
            'github_workflow_run_id' => $archive['workflow_run_id'] ?? null,
            'status' => WordpressStaticRelease::STATUS_READY,
            'storage_path' => $releasePath,
            'checksum' => $checksum,
            'size' => $size,
            'ready_at' => now(),
            'error' => null,
        ]);

        return $release->refresh();
    }

    private function repack(string $sourcePath, string $releasePath, ?string $projectPath, bool $stripRepositoryRoot = true): void
    {
        if (! class_exists(ZipArchive::class)) {
            throw new RuntimeException('The PHP ZIP extension is required to build WordPress static releases.');
        }

        $source = new ZipArchive;
        $destination = new ZipArchive;

        if ($source->open($sourcePath) !== true) {
            throw new RuntimeException('GitHub returned an invalid ZIP archive.');
        }

        if ($destination->open($releasePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            $source->close();

            throw new RuntimeException('Sitewell could not create the static release archive.');
        }

        $normalizedProjectPath = trim(str_replace('\\', '/', (string) $projectPath), '/');
        $files = 0;
        $bytes = 0;
        $hasIndex = false;

        try {
            for ($index = 0; $index < $source->numFiles; $index++) {
                $sourceName = $source->getNameIndex($index);

                if (! is_string($sourceName) || str_contains($sourceName, "\0")) {
                    throw new RuntimeException('The repository archive contains an invalid file path.');
                }

                $relativeName = $stripRepositoryRoot ? $this->relativeArchivePath($sourceName, $normalizedProjectPath) : $sourceName;

                if ($relativeName === null || str_ends_with($relativeName, '/')) {
                    continue;
                }

                $this->assertSafePath($relativeName);

                if (! $this->shouldPackage($relativeName)) {
                    continue;
                }

                $stat = $source->statIndex($index);

                if (! is_array($stat) || $bytes + $stat['size'] > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('The static site exceeds the deployment file or size limit.');
                }

                $contents = $source->getFromIndex($index);

                if (! is_string($contents)) {
                    throw new RuntimeException("Sitewell could not read {$relativeName} from the repository archive.");
                }

                if (in_array(strtolower(pathinfo($relativeName, PATHINFO_EXTENSION)), ['html', 'htm', 'xml', 'txt'], true)
                    && (preg_match('/\A(?:\xEF\xBB\xBF)?\s*---\R.*?\R---(?:\R|$)/s', $contents)
                        || preg_match('/\{%[-+]?\s*(?:include|extends|block|macro|import|from|set|if|for)\b.*?%\}/s', $contents))) {
                    throw new RuntimeException("Unbuilt template found in {$relativeName}. Configure the WordPress workflow path and artifact name, and upload the contents of the completed build output folder.");
                }

                $files++;
                $bytes += strlen($contents);

                if ($files > self::MAX_FILES || $bytes > self::MAX_EXTRACTED_BYTES) {
                    throw new RuntimeException('The static site exceeds the deployment file or size limit.');
                }

                if (! $destination->addFromString($relativeName, $contents)) {
                    throw new RuntimeException("Sitewell could not package {$relativeName}.");
                }

                $hasIndex = $hasIndex || $relativeName === 'index.html';
            }

            if (! $hasIndex) {
                throw new RuntimeException('The selected deployment files do not contain an index.html file at the root. Upload the contents of your build output folder.');
            }
        } finally {
            $source->close();
            $destination->close();
        }
    }

    private function relativeArchivePath(string $sourceName, string $projectPath): ?string
    {
        $separator = strpos($sourceName, '/');

        if ($separator === false) {
            return null;
        }

        $relativeName = substr($sourceName, $separator + 1);

        if ($relativeName === '') {
            return null;
        }

        if ($projectPath === '') {
            return $relativeName;
        }

        if ($relativeName === $projectPath.'/') {
            return null;
        }

        if (! str_starts_with($relativeName, $projectPath.'/')) {
            return null;
        }

        return substr($relativeName, strlen($projectPath) + 1);
    }

    private function assertSafePath(string $path): void
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if (str_starts_with($path, '/')
            || preg_match('/^[A-Za-z]:/', $path)
            || in_array('..', $segments, true)
            || in_array('', $segments, true)) {
            throw new RuntimeException('The repository archive contains an unsafe file path.');
        }
    }

    private function shouldPackage(string $path): bool
    {
        $segments = explode('/', str_replace('\\', '/', $path));

        if (collect($segments)->contains(fn (string $segment): bool => str_starts_with($segment, '.'))) {
            return false;
        }

        return in_array(strtolower(pathinfo($path, PATHINFO_EXTENSION)), self::ALLOWED_EXTENSIONS, true);
    }
}
