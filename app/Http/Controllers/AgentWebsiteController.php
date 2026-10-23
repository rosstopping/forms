<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadAgentEvidenceRequest;
use App\Models\Website;
use App\Services\AgentSeoEvidence;
use App\Services\AgentWebsiteDirectory;
use Illuminate\Http\JsonResponse;

class AgentWebsiteController extends Controller
{
    public function index(ReadAgentEvidenceRequest $request, AgentWebsiteDirectory $directory): JsonResponse
    {
        return response()->json($directory->index($request->user(), $request->validated()))->header('Cache-Control', 'private, no-store');
    }

    public function show(ReadAgentEvidenceRequest $request, Website $website, AgentWebsiteDirectory $directory): JsonResponse
    {
        return response()->json($directory->show($website))->header('Cache-Control', 'private, no-store');
    }

    public function evidence(ReadAgentEvidenceRequest $request, Website $website, string $section, AgentSeoEvidence $evidence): JsonResponse
    {
        return response()->json($evidence->read($website, $section, $request->validated()))->header('Cache-Control', 'private, no-store');
    }
}
