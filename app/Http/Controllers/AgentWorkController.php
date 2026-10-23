<?php

namespace App\Http\Controllers;

use App\Services\AgentWorkOverview;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentWorkController extends Controller
{
    public function __invoke(Request $request, AgentWorkOverview $overview): JsonResponse
    {
        return response()->json($overview->forUser($request->user()))
            ->header('Cache-Control', 'private, no-store');
    }
}
