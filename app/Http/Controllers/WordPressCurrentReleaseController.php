<?php

namespace App\Http\Controllers;

use App\Models\WordpressStaticRelease;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WordPressCurrentReleaseController extends Controller
{
    public function __invoke(Request $request, string $connectionId, WordPressConnectionManager $connections): JsonResponse|Response
    {
        $connection = $connections->authenticate($connectionId, $request->bearerToken());
        $release = WordpressStaticRelease::query()
            ->where('website_id', $connection->website_id)
            ->where('status', WordpressStaticRelease::STATUS_READY)
            ->whereNotNull('storage_path')
            ->whereNotNull('checksum')
            ->whereNotNull('size')
            ->latest('id')
            ->first();

        if (! $release || $request->query('active_release') === $release->public_id) {
            return response()->noContent();
        }

        return response()->json(['data' => [
            'release_id' => $release->public_id,
            'commit_sha' => $release->commit_sha,
            'checksum' => $release->checksum,
            'size' => $release->size,
            'download_url' => route('wordpress-connections.releases.download', [$connection->public_id, $release->public_id]),
            'created_at' => $release->ready_at?->toIso8601String(),
        ]]);
    }
}
