<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReadAgentEvidenceRequest;
use App\Http\Requests\StoreAgentContentTaskRequest;
use App\Http\Requests\UpdateAgentContentTaskRequest;
use App\Models\AgentContentTask;
use App\Models\Website;
use App\Services\AgentContentWork;
use Illuminate\Http\JsonResponse;

class AgentContentTaskController extends Controller
{
    public function store(StoreAgentContentTaskRequest $request, Website $website, AgentContentWork $work): JsonResponse
    {
        $task = $work->create($website, $request->user(), $request->validated());

        return response()->json(['read_only' => false, 'task' => $work->payload($task)])->header('Cache-Control', 'private, no-store');
    }

    public function show(ReadAgentEvidenceRequest $request, Website $website, AgentContentTask $agentContentTask, AgentContentWork $work): JsonResponse
    {
        $work->authorize($website, $agentContentTask, $request->user());

        return response()->json(['read_only' => true, 'task' => $work->payload($agentContentTask)])->header('Cache-Control', 'private, no-store');
    }

    public function update(UpdateAgentContentTaskRequest $request, Website $website, AgentContentTask $agentContentTask, AgentContentWork $work): JsonResponse
    {
        $work->authorize($website, $agentContentTask, $request->user());
        $task = $work->report($agentContentTask, $request->validated(), $request->routeIs('agent.tasks.blocked'));

        return response()->json(['read_only' => false, 'task' => $work->payload($task)])->header('Cache-Control', 'private, no-store');
    }
}
