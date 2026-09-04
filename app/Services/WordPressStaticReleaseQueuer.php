<?php

namespace App\Services;

use App\Jobs\BuildWordPressStaticRelease;
use App\Models\Website;
use App\Models\WordpressStaticRelease;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WordPressStaticReleaseQueuer
{
    /** @return array{release: WordpressStaticRelease, created: bool} */
    public function queue(Website $website, ?int $createdBy = null, ?string $commitSha = null): array
    {
        $repository = $website->repository;

        if (! $repository) {
            throw new RuntimeException('Connect a GitHub repository before creating a static release.');
        }

        $result = DB::transaction(function () use ($website, $repository, $createdBy, $commitSha): array {
            Website::query()->whereKey($website->getKey())->lockForUpdate()->firstOrFail();

            $query = $website->wordpressStaticReleases()
                ->whereIn('status', [
                    WordpressStaticRelease::STATUS_QUEUED,
                    WordpressStaticRelease::STATUS_BUILDING,
                    WordpressStaticRelease::STATUS_READY,
                ]);

            $existing = filled($commitSha)
                ? (clone $query)->where('commit_sha', $commitSha)->first()
                : (clone $query)->whereIn('status', [WordpressStaticRelease::STATUS_QUEUED, WordpressStaticRelease::STATUS_BUILDING])->first();

            if ($existing) {
                return ['release' => $existing, 'created' => false];
            }

            $release = $website->wordpressStaticReleases()->create([
                'created_by' => $createdBy,
                'commit_sha' => $commitSha,
                'source_ref' => $repository->default_branch,
                'status' => WordpressStaticRelease::STATUS_QUEUED,
            ]);

            return ['release' => $release, 'created' => true];
        });

        if ($result['created']) {
            BuildWordPressStaticRelease::dispatch($result['release']->id);
        }

        return $result;
    }
}
