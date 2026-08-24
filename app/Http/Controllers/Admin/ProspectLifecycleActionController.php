<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ProspectLifecycleState;
use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateProspectLifecycleRequest;
use App\Models\Prospect;
use App\Services\ProspectEngagementScorer;
use App\Services\ProspectLifecycleManager;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use InvalidArgumentException;

class ProspectLifecycleActionController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(
        UpdateProspectLifecycleRequest $request,
        Prospect $prospect,
        ProspectLifecycleManager $lifecycleManager,
        ProspectEngagementScorer $engagementScorer,
    ): RedirectResponse {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        $data = $request->validated();

        try {
            match ($data['action']) {
                'pause' => $lifecycleManager->pause($prospect, $request->user()),
                'resume' => $lifecycleManager->resume($prospect, $request->user()),
                'stop' => $lifecycleManager->stop($prospect, $request->user()),
                'mark_replied' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Replied, $request->user()),
                'mark_not_interested' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::NotInterested, $request->user()),
                'mark_future_opportunity' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::FutureOpportunity, $request->user(), CarbonImmutable::parse($data['future_opportunity_at'], 'Europe/London')->utc()),
                'mark_customer' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Customer, $request->user()),
                'mark_pilot' => $lifecycleManager->transitionManually($prospect, ProspectLifecycleState::Pilot, $request->user()),
                'force_warm' => $lifecycleManager->forceTemperature($prospect, 'warm', $request->user()),
                'force_hot' => $lifecycleManager->forceTemperature($prospect, 'hot', $request->user()),
                'clear_temperature_override' => $lifecycleManager->clearTemperatureOverride($prospect, $request->user()),
                'adjust_score' => $engagementScorer->adjust($prospect, (int) $data['score_delta'], $data['reason'], $request->user()),
                'reset_score' => $engagementScorer->adjust($prospect, -$prospect->outreachState->engagement_score, $data['reason'], $request->user()),
            };
        } catch (InvalidArgumentException $exception) {
            return back()->withErrors(['lifecycle_action' => $exception->getMessage()]);
        }

        return back()->with('status', 'Prospect outreach updated.');
    }
}
