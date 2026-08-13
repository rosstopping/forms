<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePixelHeartbeatRequest;
use App\Services\PixelHeartbeatRecorder;
use Illuminate\Http\Response;

class PixelHeartbeatController extends Controller
{
    public function __invoke(
        StorePixelHeartbeatRequest $request,
        string $siteKey,
        PixelHeartbeatRecorder $heartbeats,
    ): Response {
        $heartbeats->record(
            $siteKey,
            $request->string('url')->toString(),
            $request->string('version')->toString(),
        );

        return response()->noContent();
    }
}
