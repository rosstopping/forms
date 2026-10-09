<?php

namespace App\Services;

use App\Models\ContentPlan;
use App\Models\ContentRequest;

class ContentStrategy
{
    public function requestPauseReason(ContentPlan $plan, ContentRequest $request): ?string
    {
        if (($request->planning_status ?? 'queued') !== 'queued') {
            return 'This content is planned. Approve it for the execution queue before preparation.';
        }
        $mode = $plan->content_mode ?? 'balanced';
        if ($mode === 'balanced') {
            return null;
        }
        if ($request->work_type === 'unspecified') {
            return 'Classify this request before preparation under the website content mode.';
        }
        $isNew = in_array($request->work_type, ['new_article', 'new_page'], true);
        if ($mode === 'new_only' && ! $isNew) {
            return 'This website is set to new content only. Existing-page optimisation is paused.';
        }
        if ($mode === 'existing_only' && $isNew) {
            return 'This website is set to existing-page optimisation only. New content is paused.';
        }

        return null;
    }
}
