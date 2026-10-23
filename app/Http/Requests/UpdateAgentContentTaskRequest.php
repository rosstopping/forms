<?php

namespace App\Http\Requests;

class UpdateAgentContentTaskRequest extends ReadAgentEvidenceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        if ($this->routeIs('agent.tasks.blocked')) {
            return ['reason' => ['required', 'string', 'min:10', 'max:2000']];
        }

        return ['pull_request_url' => ['required', 'string', 'max:500', 'regex:~^https://github\.com/[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+/pull/[1-9][0-9]*$~'],
            'is_draft' => ['required', 'accepted'], 'state' => ['required', 'in:open'],
            'branch' => ['required', 'string', 'max:255'], 'head_sha' => ['required', 'regex:/^(?:[0-9a-f]{40}|[0-9a-f]{64})$/'],
            'changed_files' => ['required', 'integer', 'min:1'],
            'summary' => ['required', 'string', 'min:20', 'max:4000'], 'checks' => ['required', 'array', 'min:1', 'max:20'],
            'checks.*' => ['required', 'string', 'min:5', 'max:500']];
    }
}
