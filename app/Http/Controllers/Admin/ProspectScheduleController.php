<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ScheduleProspectOutreachRequest;
use App\Jobs\SendScheduledProspectOutreach;
use App\Models\Prospect;
use App\Services\ProspectOutreachSender;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;

class ProspectScheduleController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(ScheduleProspectOutreachRequest $request, Prospect $prospect, ProspectOutreachSender $sender): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        abort_if($eligibilityError = $sender->eligibilityError($prospect), 422, $eligibilityError);

        $scheduledFor = CarbonImmutable::parse($request->validated('scheduled_send_at'), 'Europe/London')->utc();
        $prospect->update(['scheduled_send_at' => $scheduledFor]);
        $prospect->recordActivity('send_scheduled', 'Approved outreach email scheduled for '.$scheduledFor->setTimezone('Europe/London')->format('j M Y, H:i').' UK time.', $request->user());
        SendScheduledProspectOutreach::dispatch($prospect->id, $scheduledFor)->delay($scheduledFor)->afterCommit();

        return back()->with('status', 'Outreach email scheduled for '.$scheduledFor->setTimezone('Europe/London')->format('j M Y, H:i').' UK time.');
    }
}
