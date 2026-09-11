<?php

namespace App\Http\Controllers;

use App\Services\WordPressConnectionManager;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WordPressDisconnectController extends Controller
{
    public function __invoke(
        Request $request,
        string $connectionId,
        WordPressConnectionManager $connections,
    ): Response {
        $connection = $connections->authenticate($connectionId, $request->bearerToken());
        $connections->revoke($connection);

        return response()->noContent();
    }
}
