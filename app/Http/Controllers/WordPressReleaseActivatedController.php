<?php

namespace App\Http\Controllers;

use App\Models\WordpressStaticRelease;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WordPressReleaseActivatedController extends Controller
{
    public function __invoke(
        Request $request,
        string $connectionId,
        string $releaseId,
        WordPressConnectionManager $connections,
    ): Response {
        $connection = $connections->authenticate($connectionId, $request->bearerToken());
        $release = WordpressStaticRelease::query()
            ->where('website_id', $connection->website_id)
            ->where('public_id', $releaseId)
            ->where('status', WordpressStaticRelease::STATUS_READY)
            ->firstOrFail();

        $release->update(['activated_at' => now()]);
        $connection->update([
            'active_release_public_id' => $release->public_id,
            'last_deployed_at' => now(),
            'last_seen_at' => now(),
        ]);

        return response()->noContent();
    }
}
