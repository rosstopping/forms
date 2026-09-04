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

    public function __construct(private GithubAppClient $github) {}

    public function build(WordpressStaticRelease $release, WebsiteRepository $repository): WordpressStaticRelease
    {
        $release->update(['status' => WordpressStaticRelease::STATUS_BUILDING, 'error' => null]);
        $archive = $this->github->repositoryArchive($repository, $release->commit_sha ?: $release->source_ref);

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
            $this->repack($disk->path($sourcePath), $disk->path($releasePath), $repository->project_path);
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
            'status' => WordpressStaticRelease::STATUS_READY,
            'storage_path' => $releasePath,
            'checksum' => $checksum,
            'size' => $size,
            'ready_at' => now(),
            'error' => null,
        ]);

        return $release->refresh();
    }

    private function repack(string $sourcePath, string $releasePath, ?string $projectPath): void
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

                $relativeName = $this->relativeArchivePath($sourceName, $normalizedProjectPath);

                if ($relativeName === null || str_ends_with($relativeName, '/')) {
                    continue;
                }

                $this->assertSafePath($relativeName);
                $contents = $source->getFromIndex($index);

                if (! is_string($contents)) {
                    throw new RuntimeException("Sitewell could not read {$relativeName} from the repository archive.");
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
                throw new RuntimeException('The selected repository path does not contain an index.html file.');
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
}
