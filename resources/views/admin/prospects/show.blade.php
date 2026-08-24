@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><a href="{{ route('admin.prospects.index') }}" class="text-sm font-medium text-slate-600">← Outreach</a><div class="mt-3 flex flex-wrap items-center gap-3"><h1 class="text-3xl font-semibold tracking-tight">{{ $prospect->business_name }}</h1><span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str($prospect->status)->replace('_', ' ')->title() }}</span><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-red-100 text-red-800' => $prospect->lead_temperature === 'hot', 'bg-orange-100 text-orange-800' => $prospect->lead_temperature === 'warm', 'bg-sky-100 text-sky-800' => $prospect->lead_temperature === 'cold'])>{{ str($prospect->lead_temperature)->title() }} lead</span>@unless ($prospect->website_url)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">Website opportunity</span>@endunless</div>@if ($prospect->website_url)<a href="{{ $prospect->website_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-sm text-teal-700 hover:underline">{{ $prospect->website_url }} ↗</a>@else<p class="mt-1 text-sm text-slate-500">No website was linked from the public listing.</p>@endif</div>
        <div class="flex flex-wrap gap-2">
            @unless ($isFreeSiteAudit)
            @if ($prospect->website_url && ! in_array($prospect->analysis_status, ['pending', 'running']))<form method="POST" action="{{ route('admin.prospects.analyse', $prospect) }}">@csrf<button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium">Research again</button></form>@endif
            @if ($prospect->outreach_body && ! $prospect->approved_at)<form method="POST" action="{{ route('admin.prospects.approve', $prospect) }}">@csrf<button class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700">Approve draft</button></form>@endif
            @if ($prospect->outreach_subject && $prospect->outreach_body)<form method="POST" action="{{ route('admin.prospects.test-email', $prospect) }}">@csrf<button type="submit" class="rounded-lg border border-slate-950/10 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Send test to {{ Auth::user()->email }}</button></form>@endif
            @if ($prospect->approved_at && (! $prospect->sent_at || $prospect->isOutreachFollowUpDue()))
                @unless ($prospect->sent_at)
                <form method="POST" action="{{ route('admin.prospects.schedule', $prospect) }}" class="flex flex-wrap items-center gap-2 rounded-lg border border-slate-300 bg-white p-1">
                    @csrf
                    <label for="scheduled_send_at" class="sr-only">Schedule email (UK time)</label>
                    <input id="scheduled_send_at" type="datetime-local" name="scheduled_send_at" value="{{ old('scheduled_send_at', $prospect->scheduled_send_at?->setTimezone('Europe/London')->format('Y-m-d\TH:i')) }}" min="{{ now('Europe/London')->addMinute()->format('Y-m-d\TH:i') }}" required class="rounded-md border-0 px-2 py-1 text-sm">
                    <button class="rounded-md border border-slate-300 px-3 py-1.5 text-sm font-semibold text-slate-700">{{ $prospect->scheduled_send_at ? 'Reschedule email' : 'Schedule email' }}</button>
                </form>
                @endunless
                <form method="POST" action="{{ route('admin.prospects.send', $prospect) }}">@csrf<button class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Send approved email</button></form>
            @endif
            @endunless
            <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}" data-confirm-action-form>
                @csrf
                @method('DELETE')
                <button type="button" data-confirm-action data-confirm-title="Delete this prospect?" data-confirm-message="The prospect, website research, outreach draft, and activity history will be permanently deleted. This cannot be undone." data-confirm-label="Delete prospect" data-confirm-danger class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
            </form>
        </div>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @error('scheduled_send_at')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
    @foreach (['video_url', 'subject', 'body', 'action'] as $videoField)@error($videoField)<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror @endforeach
    @error('lifecycle_action')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $message }}</div>@enderror
    @if ($prospect->analysis_status === 'failed')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Research failed:</strong> {{ $prospect->analysis_error }}</div>@endif
    @if ($prospect->outreachState->manual_follow_up_required_at)
        <section class="rounded-xl border border-violet-200 bg-violet-50 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wider text-violet-700">Personal action recommended</p><h2 class="mt-1 text-lg font-semibold text-violet-950">Follow up directly</h2><p class="mt-1 text-sm text-violet-800">Engagement continued after the personalised video. Automatic email is paused so you can decide the next conversation.</p></div><span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-violet-800 shadow-sm">Score {{ $prospect->outreachState->engagement_score }}</span></div>
            <ul class="mt-4 grid gap-2 sm:grid-cols-2">
                @foreach ($prospect->outreachState->manual_follow_up_reason ?? [] as $reason)
                    <li class="flex items-center justify-between gap-3 rounded-lg bg-white px-3 py-2 text-sm text-slate-700"><span class="font-medium">{{ $reason['label'] }}</span><span class="shrink-0 text-xs text-slate-400">{{ \Carbon\CarbonImmutable::parse($reason['last_at'])->diffForHumans() }}</span></li>
                @endforeach
            </ul>
        </section>
    @endif
    <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Manual control</p><h2 class="mt-1 text-lg font-semibold">Outreach lifecycle</h2><p class="mt-1 text-sm text-slate-500">{{ str($prospect->outreachState->lifecycle_state->value)->replace('_', ' ')->title() }} · Automation {{ $prospect->outreachState->automation_status->value }} · Score {{ $prospect->outreachState->engagement_score }}</p></div></div>
        <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
            @foreach ([
                ['pause', 'Pause automation', 'Temporarily prevents scheduled sequence actions while keeping the prospect’s place.'],
                ['resume', 'Resume automation', 'Restarts due sequence actions for a prospect that is not in a stopped lifecycle state.'],
                ['force_warm', 'Force Warm', 'Manually classifies this prospect as warm regardless of their current engagement score.'],
                ['force_hot', 'Force Hot', 'Moves the prospect into the personalised-video queue and pauses cold follow-ups.'],
                ['clear_temperature_override', 'Use automatic score', 'Removes the manual Warm or Hot override and calculates temperature from the engagement score.'],
            ] as [$action, $label, $description])
                <form method="POST" action="{{ route('admin.prospects.lifecycle', $prospect) }}" class="flex flex-col items-start rounded-lg border border-slate-200 bg-slate-50 p-3">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $action }}"><button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ $label }}</button><p class="mt-2 text-xs leading-5 text-slate-500">{{ $description }}</p></form>
            @endforeach
            @foreach ([
                ['stop', 'Stop outreach', 'Stops all normal automated outreach without changing the prospect’s sales outcome.'],
                ['mark_replied', 'Mark replied', 'Records a reply, cancels future automated messages, and moves the prospect to manual handling.'],
                ['mark_not_interested', 'Not interested', 'Records that the prospect declined and permanently stops the normal outreach sequence.'],
                ['mark_customer', 'Customer', 'Marks the prospect as converted into a customer and stops all prospecting emails.'],
                ['mark_pilot', 'Pilot', 'Marks the prospect as an active pilot and stops all prospecting emails.'],
            ] as [$action, $label, $description])
                <form method="POST" action="{{ route('admin.prospects.lifecycle', $prospect) }}" data-confirm-action-form class="flex flex-col items-start rounded-lg border border-slate-200 bg-slate-50 p-3">@csrf @method('PATCH')<input type="hidden" name="action" value="{{ $action }}"><button type="button" data-confirm-action data-confirm-title="{{ $label }}?" data-confirm-message="This action stops the normal automated outreach sequence." data-confirm-label="{{ $label }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-semibold text-slate-700 hover:bg-slate-100">{{ $label }}</button><p class="mt-2 text-xs leading-5 text-slate-500">{{ $description }}</p></form>
            @endforeach
        </div>
        <div class="mt-5 border-t border-slate-100 pt-5"><h3 class="text-sm font-semibold text-slate-900">Dates and engagement score</h3><p class="mt-1 text-sm text-slate-500">Use these tools when a prospect should be revisited later or their automated engagement score needs a manual correction.</p></div>
        <div class="mt-4 grid gap-4 lg:grid-cols-2">
            <form method="POST" action="{{ route('admin.prospects.lifecycle', $prospect) }}" class="rounded-lg border border-violet-200 bg-violet-50/60 p-4">@csrf @method('PATCH')<input type="hidden" name="action" value="mark_future_opportunity"><div><label for="future_opportunity_at" class="text-sm font-semibold text-violet-950">Contact later <span class="font-normal text-violet-700">(UK time)</span></label><p class="mt-1 text-xs leading-5 text-violet-800">Stops the current sequence and stores a date for this prospect to return as a future opportunity. It will not automatically restart outreach.</p><input id="future_opportunity_at" type="datetime-local" name="future_opportunity_at" required min="{{ now('Europe/London')->addMinute()->format('Y-m-d\TH:i') }}" class="mt-3 w-full rounded-lg border border-violet-200 bg-white px-3 py-2 text-sm"></div><button class="mt-3 rounded-lg border border-violet-300 bg-white px-3 py-2 text-sm font-semibold text-violet-800 hover:bg-violet-100">Mark future opportunity</button></form>
            <div class="rounded-lg border border-slate-200 bg-slate-50 p-4"><form method="POST" action="{{ route('admin.prospects.lifecycle', $prospect) }}">@csrf @method('PATCH')<input type="hidden" name="action" value="adjust_score"><p class="text-sm font-semibold text-slate-900">Adjust engagement score</p><p class="mt-1 text-xs leading-5 text-slate-500">Add or subtract points to correct the automated score. Enter the amount under Score ± and record why under Reason; the change remains in the activity history.</p><div class="mt-3 grid gap-2 sm:grid-cols-[6rem_1fr_auto] sm:items-end"><div><label for="score_delta" class="text-sm font-medium">Score ±</label><input id="score_delta" type="number" name="score_delta" min="-100" max="100" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div><div><label for="score_reason" class="text-sm font-medium">Reason</label><input id="score_reason" name="reason" required class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div><button class="rounded-lg bg-slate-900 px-3 py-2 text-sm font-semibold text-white">Adjust</button></div></form><form method="POST" action="{{ route('admin.prospects.lifecycle', $prospect) }}" data-confirm-action-form class="mt-4 border-t border-slate-200 pt-3">@csrf @method('PATCH')<input type="hidden" name="action" value="reset_score"><input type="hidden" name="reason" value="Score reset manually"><button type="button" data-confirm-action data-confirm-title="Reset engagement score?" data-confirm-message="The current score will return to zero. The immutable engagement history remains available." data-confirm-label="Reset score" class="text-sm font-semibold text-red-700 hover:underline">Reset engagement score</button><p class="mt-1 text-xs leading-5 text-slate-500">Returns the score to zero without deleting the prospect’s engagement events or activity history.</p></form></div>
        </div>
    </section>
    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <div class="space-y-6">
            @if ($isFreeSiteAudit)
                <section class="rounded-xl border border-teal-200 bg-teal-50 p-5 shadow-sm">
                    <h2 class="font-semibold text-teal-950">Free audit results email</h2>
                    @if ($prospect->activities->contains('type', 'free_audit_email_sent'))
                        <p class="mt-2 text-sm text-teal-800">The audit results were emailed automatically to {{ $prospect->email }}.</p>
                    @elseif ($prospect->activities->contains('type', 'free_audit_email_failed'))
                        <p class="mt-2 text-sm text-red-700">The audit completed, but the results email could not be delivered. Check the queue failure and mail configuration.</p>
                    @else
                        <p class="mt-2 text-sm text-teal-800">The audit is complete and its results email is queued for automatic delivery. No approval or outreach action is required.</p>
                    @endif
                </section>
            @else
            @if ($needsPersonalisedVideo)
                <section id="personalised-video" class="scroll-mt-6 rounded-xl border border-red-200 bg-red-50 p-5 shadow-sm">
                    <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wider text-red-700">Hot lead · manual action</p><h2 class="mt-1 text-lg font-semibold text-red-950">Add / Send Personalised Video</h2><p class="mt-1 text-sm text-red-800">Cold automation is paused. Paste the video you recorded, review the message, then send or schedule it.</p></div><span class="rounded-full bg-white px-3 py-1 text-xs font-bold text-red-800 shadow-sm">Score {{ $prospect->outreachState->engagement_score }}</span></div>
                    @if ($scheduledVideoDelivery)
                        <p class="mt-4 rounded-lg border border-violet-200 bg-violet-50 px-3 py-2 text-sm text-violet-800">Scheduled for {{ $scheduledVideoDelivery->scheduled_at->setTimezone('Europe/London')->format('j M Y, H:i') }} UK time. Saving another schedule will replace it; Send now will send the reserved message immediately.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.prospects.personalised-video', $prospect) }}" class="mt-4 space-y-4">
                        @csrf
                        <div><label for="video_url" class="text-sm font-semibold text-slate-800">Personalised video URL</label><input id="video_url" type="url" name="video_url" required value="{{ old('video_url', $prospect->showcase_video_url) }}" placeholder="https://www.loom.com/share/..." class="mt-1 w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm focus:border-red-400 focus:ring-red-400"></div>
                        <div><label for="video_subject" class="text-sm font-semibold text-slate-800">Subject</label><input id="video_subject" name="subject" required value="{{ old('subject', $scheduledVideoDelivery?->subject ?? $personalisedVideoSubject) }}" class="mt-1 w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm focus:border-red-400 focus:ring-red-400"></div>
                        <div><label for="video_body" class="text-sm font-semibold text-slate-800">Message</label><textarea id="video_body" name="body" rows="8" required class="mt-1 w-full rounded-lg border border-red-200 bg-white px-3 py-2 text-sm focus:border-red-400 focus:ring-red-400">{{ old('body', $scheduledVideoDelivery?->body ?? $personalisedVideoBody) }}</textarea></div>
                        <div class="flex flex-col gap-3 rounded-lg border border-red-100 bg-white/70 p-3 sm:flex-row sm:items-end">
                            <div class="flex-1"><label for="video_scheduled_send_at" class="text-sm font-semibold text-slate-800">Schedule time <span class="font-normal text-slate-500">(optional, UK time)</span></label><input id="video_scheduled_send_at" type="datetime-local" name="scheduled_send_at" value="{{ old('scheduled_send_at', $scheduledVideoDelivery?->scheduled_at?->setTimezone('Europe/London')->format('Y-m-d\TH:i')) }}" min="{{ now('Europe/London')->addMinute()->format('Y-m-d\TH:i') }}" class="mt-1 w-full rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm"></div>
                            <div class="flex flex-wrap gap-2"><button type="submit" name="action" value="schedule" class="rounded-lg border border-red-300 bg-white px-4 py-2 text-sm font-semibold text-red-800 hover:bg-red-50">Schedule video</button><button type="submit" name="action" value="send_now" class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Send video now</button></div>
                        </div>
                    </form>
                </section>
            @endif
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Outreach draft</h2><p class="text-sm text-slate-500">Editing the draft or its video link resets approval.</p></div>@if ($prospect->approved_at)<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Approved</span>@endif</div>
                <form method="POST" action="{{ route('admin.prospects.update', $prospect) }}" class="mt-4 space-y-4">@csrf @method('PUT')
                    <input type="hidden" name="business_name" value="{{ $prospect->business_name }}"><input type="hidden" name="contact_name" value="{{ $prospect->contact_name }}"><input type="hidden" name="email" value="{{ $prospect->email }}"><input type="hidden" name="website_url" value="{{ $prospect->website_url }}"><input type="hidden" name="status" value="{{ $prospect->status }}">
                    <div><label for="outreach_subject" class="text-sm font-medium">Subject</label><input id="outreach_subject" name="outreach_subject" value="{{ old('outreach_subject', $prospect->outreach_subject) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Waiting for research…"></div>
                    <div><label for="outreach_body" class="text-sm font-medium">Message</label><textarea id="outreach_body" name="outreach_body" rows="11" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Waiting for research…">{{ old('outreach_body', $prospect->outreach_body) }}</textarea></div>
                    <div><label for="showcase_video_url" class="text-sm font-medium">Showcase video URL</label><input id="showcase_video_url" type="url" name="showcase_video_url" value="{{ old('showcase_video_url', $prospect->showcase_video_url) }}" placeholder="https://www.loom.com/share/..." class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"><p class="mt-1 text-xs text-slate-500">This prospect-specific link appears behind the video button in test and live emails.</p>@error('showcase_video_url')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror</div>
                    <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Save draft</button>
                </form>
            </section>
            @endif
        </div>
        <div class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div><h2 class="font-semibold">Public contact details</h2><p class="text-sm text-slate-500">Published details found on the business website. Check the linked source before using them.</p></div>
                @if ($prospect->analysis_status === 'pending' || $prospect->analysis_status === 'running')
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Contact discovery runs with the website research.</p>
                @elseif (filled(data_get($prospect->contact_details, 'emails')) || filled(data_get($prospect->contact_details, 'phones')) || filled(data_get($prospect->contact_details, 'addresses')) || data_get($prospect->contact_details, 'contact_form_url'))
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach (data_get($prospect->contact_details, 'emails', []) as $email)
                            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Published email</dt><dd class="mt-1"><a href="mailto:{{ $email['value'] }}" class="font-medium text-teal-700 hover:underline">{{ $email['value'] }}</a><a href="{{ $email['source_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-xs text-slate-500 hover:underline">View source ↗</a></dd></div>
                        @endforeach
                        @foreach (data_get($prospect->contact_details, 'phones', []) as $phone)
                            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Published phone</dt><dd class="mt-1"><a href="tel:{{ $phone['value'] }}" class="font-medium text-teal-700 hover:underline">{{ $phone['value'] }}</a><a href="{{ $phone['source_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-xs text-slate-500 hover:underline">View source ↗</a></dd></div>
                        @endforeach
                        @foreach (data_get($prospect->contact_details, 'addresses', []) as $address)
                            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Published address</dt><dd class="mt-1"><span class="font-medium text-slate-800">{{ $address['value'] }}</span><a href="{{ $address['source_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-xs text-slate-500 hover:underline">View source ↗</a></dd></div>
                        @endforeach
                        @if (data_get($prospect->contact_details, 'contact_form_url'))
                            <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Contact form</dt><dd class="mt-1"><a href="{{ data_get($prospect->contact_details, 'contact_form_url') }}" target="_blank" rel="noopener noreferrer" class="font-medium text-teal-700 hover:underline">Open contact form ↗</a></dd></div>
                        @endif
                    </dl>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-sm text-slate-600">No clearly published email address, phone number, or contact form was found.</p>
                @endif
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Prospect details</h2><form method="POST" action="{{ route('admin.prospects.update', $prospect) }}" class="mt-4 space-y-4">@csrf @method('PUT')
                <input type="hidden" name="outreach_subject" value="{{ $prospect->outreach_subject }}"><input type="hidden" name="outreach_body" value="{{ $prospect->outreach_body }}"><input type="hidden" name="showcase_video_url" value="{{ $prospect->showcase_video_url }}">
                <div><label class="text-sm font-medium">Business</label><input name="business_name" value="{{ $prospect->business_name }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label class="text-sm font-medium">Contact</label><input name="contact_name" value="{{ $prospect->contact_name }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label class="text-sm font-medium">Email</label><input type="email" name="email" value="{{ $prospect->email }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label class="text-sm font-medium">Website <span class="font-normal text-slate-500">(optional)</span></label><input type="url" name="website_url" value="{{ $prospect->website_url }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-medium">Stage</label><select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\Prospect::STATUSES as $status)<option value="{{ $status }}" @selected($prospect->status === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div><div><label class="text-sm font-medium">Next follow-up</label><input type="datetime-local" name="next_follow_up_at" value="{{ $prospect->next_follow_up_at?->format('Y-m-d\\TH:i') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></div>
                <div><label class="text-sm font-medium">Notes</label><textarea name="notes" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $prospect->notes }}</textarea></div><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="suppressed" value="1" @checked($prospect->suppressed_at) class="rounded border-slate-300">Never send email to this prospect</label><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Save prospect</button>
            </form></section>
            @if ($delivery = $prospect->outreachDeliveries->first())
                <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div><h2 class="font-semibold">Email engagement</h2><p class="text-sm text-slate-500">Tracking can be affected by privacy protection and email security scanners.</p></div>
                    <dl class="mt-4 grid gap-4 sm:grid-cols-2">
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Opened</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $delivery->first_opened_at?->format('j M Y, H:i') ?? 'Not recorded' }}</dd>@if ($delivery->open_count)<dd class="text-xs text-slate-500">{{ $delivery->open_count }} {{ str('open')->plural($delivery->open_count) }} · last {{ $delivery->last_opened_at->diffForHumans() }}</dd>@endif</div>
                        <div><dt class="text-xs font-semibold uppercase tracking-wide text-slate-500">Clicked</dt><dd class="mt-1 text-sm font-medium text-slate-900">{{ $delivery->first_clicked_at?->format('j M Y, H:i') ?? 'Not recorded' }}</dd>@if ($delivery->click_count)<dd class="text-xs text-slate-500">{{ $delivery->click_count }} {{ str('click')->plural($delivery->click_count) }} · last {{ $delivery->last_clicked_at->diffForHumans() }}</dd>@endif</div>
                    </dl>
                    <div class="mt-4 grid gap-2">@foreach ($delivery->links as $link)<div class="flex items-center justify-between gap-3 rounded-lg bg-slate-50 px-3 py-2 text-sm"><span class="font-medium text-slate-700">{{ $link->label }}</span><span class="text-xs text-slate-500">{{ $link->click_count ? $link->click_count.' '.str('click')->plural($link->click_count).' · '.$link->last_clicked_at->diffForHumans() : 'Not clicked' }}</span></div>@endforeach</div>
                </section>
            @endif
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div><h2 class="font-semibold">Activity timeline</h2><p class="text-sm text-slate-500">Lifecycle, outreach, engagement, scoring, and manual changes.</p></div><ol class="mt-5 space-y-0">@forelse ($prospect->activities as $activity)<li class="relative flex gap-3 pb-5 before:absolute before:bottom-0 before:left-[0.3125rem] before:top-3 before:w-px before:bg-slate-200 last:pb-0 last:before:hidden"><span @class(['relative z-10 mt-1 size-2.5 shrink-0 rounded-full ring-4 ring-white', 'bg-emerald-500' => in_array($activity->type, ['email_clicked', 'email_opened', 'engagement_score_changed', 'reply_detected'], true), 'bg-violet-500' => str_contains($activity->type, 'video') || str_contains($activity->type, 'manual_follow_up'), 'bg-red-500' => str_contains($activity->type, 'stopped') || str_contains($activity->type, 'cancelled'), 'bg-slate-400' => ! in_array($activity->type, ['email_clicked', 'email_opened', 'engagement_score_changed', 'reply_detected'], true) && ! str_contains($activity->type, 'video') && ! str_contains($activity->type, 'manual_follow_up') && ! str_contains($activity->type, 'stopped') && ! str_contains($activity->type, 'cancelled')])></span><div class="min-w-0"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ str($activity->type)->replace('_', ' ')->headline() }}</p><p class="mt-0.5 text-sm text-slate-800">{{ $activity->description }}</p><p class="mt-1 text-xs text-slate-500"><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->format('j M Y, H:i') }}</time> · {{ $activity->created_at->diffForHumans() }} · {{ $activity->user?->name ?: 'System' }}</p></div></li>@empty<li class="text-sm text-slate-500">No activity yet.</li>@endforelse</ol></section>
        </div>
    </div>

    <details class="group rounded-xl border border-slate-200 bg-white shadow-sm">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 marker:content-none">
            <div><h2 class="font-semibold text-slate-900">Website opportunities</h2><p class="mt-1 text-sm text-slate-500">Verified website findings used to personalise the outreach draft.</p></div>
            <div class="flex shrink-0 items-center gap-3">@if ($prospect->opportunity_score !== null)<div class="rounded-lg bg-amber-50 px-3 py-1.5 text-center"><p class="font-semibold text-amber-800">{{ $prospect->opportunity_score }}</p><p class="text-[9px] font-semibold uppercase tracking-wide text-amber-700">Opportunity</p></div>@endif<span class="grid size-8 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180" aria-hidden="true">⌄</span></div>
        </summary>
        <div class="space-y-3 border-t border-slate-100 p-5">@if (! $prospect->website_url)<div class="rounded-lg bg-violet-50 p-4 text-sm text-violet-800">Website audit skipped. This prospect is being treated as an opportunity for a new website.</div>@elseif (in_array($prospect->analysis_status, ['pending', 'running']))<div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Website research is {{ $prospect->analysis_status }}. The draft will appear here automatically when the queue worker finishes.</div>@else @forelse ($prospect->findings ?? [] as $finding)<div class="rounded-lg border border-slate-200 p-3"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $finding['severity'] === 'failed' ? 'bg-red-500' : 'bg-amber-400' }}"></span><p class="text-sm font-semibold">{{ $finding['title'] }}</p></div><p class="mt-1 text-sm text-slate-600">{{ $finding['message'] }}</p></div>@empty<div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">No clear homepage issues were found. Review the general introduction carefully before approving it.</div>@endforelse @endif</div>
    </details>

    <dialog data-confirm-action-dialog class="m-auto w-[min(30rem,calc(100%-2rem))] rounded-xl border border-slate-200 bg-white p-0 shadow-2xl backdrop:bg-slate-950/50">
        <div class="p-6">
            <h2 data-confirm-action-title class="text-lg font-semibold text-slate-950">Confirm action</h2>
            <p data-confirm-action-message class="mt-2 text-sm leading-6 text-slate-600"></p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" data-confirm-action-cancel class="rounded-md border border-slate-300 bg-white px-4 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Cancel</button>
                <button type="button" data-confirm-action-submit class="rounded-md bg-slate-900 px-4 py-2 text-sm font-medium text-white hover:bg-slate-800">Confirm</button>
            </div>
        </div>
    </dialog>
</div>
@endsection
