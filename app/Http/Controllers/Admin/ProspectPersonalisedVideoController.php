<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SendProspectPersonalisedVideoRequest;
use App\Models\Prospect;
use App\Services\ProspectPersonalisedVideo;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use LogicException;

class ProspectPersonalisedVideoController extends Controller
{
    /**
     * Handle the incoming request.
     */
    public function __invoke(SendProspectPersonalisedVideoRequest $request, Prospect $prospect, ProspectPersonalisedVideo $personalisedVideo): RedirectResponse
    {
        abort_unless($prospect->isAccessibleBy($request->user()), 403);
        $data = $request->validated();

        try {
            if ($data['action'] === 'schedule') {
                $scheduledFor = CarbonImmutable::parse($data['scheduled_send_at'], 'Europe/London')->utc();
                $personalisedVideo->schedule($prospect, $data['video_url'], $data['subject'], $data['body'], $scheduledFor, $request->user());

                return back()->with('status', 'Personalised video scheduled for '.$scheduledFor->setTimezone('Europe/London')->format('j M Y, H:i').' UK time.');
            }

            $personalisedVideo->sendNow($prospect, $data['video_url'], $data['subject'], $data['body'], $request->user());
        } catch (LogicException $exception) {
            abort(422, $exception->getMessage());
        }

        return back()->with('status', 'Personalised video sent. Engagement tracking is active.');
    }
}
