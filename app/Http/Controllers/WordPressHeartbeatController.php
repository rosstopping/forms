<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWordPressHeartbeatRequest;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\Response;

class WordPressHeartbeatController extends Controller
{
    public function __invoke(
        StoreWordPressHeartbeatRequest $request,
        string $connectionId,
        WordPressConnectionManager $connections,
    ): Response {
        $connections->heartbeat(
            $connectionId,
            $request->bearerToken(),
            $request->string('site_url')->toString(),
            $request->string('plugin_version')->toString(),
        );

        return response()->noContent();
    }
}
