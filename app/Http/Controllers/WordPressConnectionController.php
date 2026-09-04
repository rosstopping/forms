<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreWordPressConnectionRequest;
use App\Services\WordPressConnectionManager;
use Illuminate\Http\JsonResponse;

class WordPressConnectionController extends Controller
{
    public function __invoke(StoreWordPressConnectionRequest $request, WordPressConnectionManager $connections): JsonResponse
    {
        $result = $connections->connect(
            $request->string('code')->toString(),
            $request->string('site_url')->toString(),
            $request->string('plugin_version')->toString(),
        );
        $connection = $result['connection'];

        return response()->json([
            'data' => [
                'connection_id' => $connection->public_id,
                'credential' => $result['credential'],
                'website' => [
                    'name' => $connection->website->name,
                    'domain' => $connection->website->domains->firstWhere('is_primary', true)?->domain
                        ?? $connection->website->domains->first()?->domain,
                ],
                'heartbeat_url' => route('wordpress-connections.heartbeat', $connection->public_id),
                'disconnect_url' => route('wordpress-connections.disconnect', $connection->public_id),
            ],
        ], 201);
    }
}
