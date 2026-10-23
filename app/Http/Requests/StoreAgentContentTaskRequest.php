<?php

namespace App\Http\Requests;

use App\Services\AgentSeoEvidence;
use Illuminate\Validation\Rule;

class StoreAgentContentTaskRequest extends ReadAgentEvidenceRequest
{
    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'scheduled_for' => ['required', 'date_format:Y-m-d'],
            'instructions' => ['required', 'string', 'min:30', 'max:12000'],
            'work_type' => ['required', Rule::in(['new_article', 'optimisation'])],
            'rationale' => ['required', 'string', 'min:30', 'max:3000'],
            'host' => ['required', 'string', 'max:100'], 'path' => ['required', 'string', 'max:1000'],
            'repository' => ['required', 'string', 'max:255', 'regex:~^[a-zA-Z0-9_.-]+/[a-zA-Z0-9_.-]+$~'],
            'coverage_reviewed' => ['required', 'accepted'], 'repository_reviewed' => ['required', 'accepted'], 'open_prs_reviewed' => ['required', 'accepted'],
            'target_urls' => ['required', 'array', 'min:1', 'max:5'], 'target_urls.*' => ['required', 'string', 'url:http,https', 'max:700', 'distinct'],
            'target_queries' => ['present', 'array', 'max:5'], 'target_queries.*' => ['required', 'string', 'max:100', 'distinct'],
            'target_files' => ['present', 'array', 'max:20'],
            'target_files.*' => ['required', 'string', 'max:500', 'regex:~^[a-zA-Z0-9_.-][a-zA-Z0-9_./-]*$~', 'not_regex:~(^|/)\.{1,2}(/|$)|(^|/)(\.git|\.env)(/|$|\.)~', 'distinct'],
            'evidence' => ['required', 'array', 'min:1', 'max:5'], 'evidence.*' => ['required', 'array:section,id'],
            'evidence.*.section' => ['required', Rule::in(AgentSeoEvidence::SECTIONS)], 'evidence.*.id' => ['required', 'integer', 'min:1'],
        ];
    }
}
