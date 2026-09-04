<?php

namespace App\Http\Controllers;

use App\Models\WordpressStaticRelease;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class WordPressReleaseDownloadController extends Controller
{
    public function __invoke(
        Request $request,
        string $connectionId,
        string $releaseId,
        WordPressConnectionManager $connections,
    ): StreamedResponse {
        $connection = $connections->authenticate($connectionId, $request->bearerToken());
        $release = WordpressStaticRelease::query()
            ->where('website_id', $connection->website_id)
            ->where('public_id', $releaseId)
            ->where('status', WordpressStaticRelease::STATUS_READY)
            ->firstOrFail();

        abort_unless(filled($release->storage_path) && Storage::disk('local')->exists($release->storage_path), 404);

        return Storage::disk('local')->download(
            $release->storage_path,
            $release->public_id.'.zip',
            ['Cache-Control' => 'private, no-store'],
        );
    }
}
