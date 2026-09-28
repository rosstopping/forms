<section class="ui-panel ui-section" aria-labelledby="sequence-heading">
    <div class="space-y-2">
        <h2 id="sequence-heading" class="text-lg font-semibold text-balance">What sends &amp; when</h2>
        <p class="text-base/6 text-pretty text-slate-500 sm:text-sm/6">Open each step to read the message. Upcoming content uses the saved draft; sent content is the delivery record.</p>
        <p class="text-base text-slate-700 sm:text-sm">Video in initial email: <strong class="font-medium">{{ $outreachPlan['video_in_initial'] ? 'Yes' : 'No' }}</strong>{{ $prospect->sent_at ? '' : ' (draft)' }}</p>
    </div>
    <ol role="list" class="mt-5 divide-y divide-slate-950/10">
        @foreach ($outreachPlan['rows'] as $step)
            <li class="py-4 first:pt-0 last:pb-0">
                <details @if ($step['status'] === 'Scheduled') open @endif>
                    <summary class="cursor-pointer text-base sm:text-sm">
                        <span class="font-semibold text-slate-900">{{ $step['title'] }}</span>
                        <span class="font-normal text-slate-500"> · {{ $step['status'] }}</span>
                        <p class="mt-1 text-base text-slate-500 tabular-nums sm:text-sm">{{ $step['timing'] }}</p>
                    </summary>
                    <div class="mt-4 space-y-3 text-base/6 sm:text-sm/6">
                        <p class="text-pretty text-slate-500">{{ $step['note'] }}</p>
                        @if ($step['subject'] || $step['body'])
                            <div class="ui-well p-4">
                                <p class="break-words font-medium text-slate-900">{{ $step['subject'] ?: 'No subject yet' }}</p>
                                <p class="mt-3 whitespace-pre-line break-words text-slate-700">{{ $step['body'] ?: 'No message saved yet.' }}</p>
                            </div>
                        @else
                            <p class="text-slate-500">Save a draft to preview the message here.</p>
                        @endif
                        <p class="text-slate-500"><strong class="font-medium text-slate-700">Email blocks:</strong> {{ $step['blocks'] }}</p>
                    </div>
                </details>
            </li>
        @endforeach
    </ol>
    <details class="mt-5 border-t border-slate-950/10 pt-5">
        <summary class="cursor-pointer text-base font-medium text-slate-800 sm:text-sm">How this sequence works</summary>
        <ul role="list" class="mt-3 space-y-3 text-base/6 text-slate-500 sm:text-sm/6">
            <li>The first follow-up is due {{ config('outreach.timing.cold_retry_days') }} days after the initial email. It mentions the video only if the initial delivery included one; otherwise it reuses the saved initial message.</li>
            <li>The final follow-up is due {{ config('outreach.timing.final_follow_up_days') }} days after the first follow-up. It currently refers to the website audit.</li>
            <li>At most {{ config('outreach.maximum_follow_up_attempts') }} cold follow-ups send. A later completion check closes the sequence without another email.</li>
            <li>A recorded reply, suppression, stopped outcome, paused automation or meaningful engagement prevents cold follow-ups. Mark replies here if they have not been recorded automatically.</li>
            <li>The separate personalised-video email is manual. No automatic email follows it, including older pending video follow-ups.</li>
            <li>Conditional timings are estimates. Sending requires approval and eligibility; actual delivery can be later if the queue is delayed.</li>
        </ul>
    </details>
</section>
