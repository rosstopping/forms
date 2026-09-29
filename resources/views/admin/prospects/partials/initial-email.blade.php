<details class="ui-panel" @if (! $prospect->sent_at && ! $needsPersonalisedVideo) open @endif>
    <summary class="cursor-pointer p-5 sm:p-6">
        <span class="font-semibold">Initial email</span>
        <span class="font-normal text-slate-500"> · {{ $prospect->sent_at ? 'Previously sent' : ($prospect->approved_at ? 'Approved draft' : 'Draft') }}</span>
    </summary>
    <div class="border-t border-slate-950/10 p-5 sm:p-6">
        <p class="text-base/6 text-slate-500 sm:text-sm/6">Editing the draft, video link or audit option resets approval. Save before testing or approving. Sent emails stay unchanged.</p>
                <form method="POST" action="{{ route('admin.prospects.update', $prospect) }}" class="mt-4 space-y-4" data-outreach-draft-editor>@csrf @method('PUT')
                    <div class="ui-well space-y-3 p-4">
                        <label for="outreach_template" class="ui-label">Start from a template</label>
                        <div class="flex flex-wrap gap-2">
                            <select id="outreach_template" class="ui-input min-w-0 flex-1" data-outreach-template>
                                <option value="">Choose a template</option>
                                @foreach ($outreachDraftTemplates as $key => $template)
                                    <option value="{{ $key }}" data-subject="{{ $template['subject'] }}" data-body="{{ $template['body'] }}">{{ $template['label'] }}</option>
                                @endforeach
                            </select>
                            <button type="button" class="ui-button ui-button-secondary" data-outreach-template-apply disabled>Use template</button>
                        </div>
                        <p class="text-base text-slate-500 sm:text-sm">Replaces the subject and message below. Edit the wording for a first introduction, add your video URL if needed, then save the draft.</p>
                        <p class="text-base text-slate-600 sm:text-sm" data-outreach-template-status role="status"></p>
                    </div>
                    <input type="hidden" name="suppressed" value="{{ $prospect->suppressed_at ? 1 : 0 }}"><input type="hidden" name="business_name" value="{{ $prospect->business_name }}"><input type="hidden" name="contact_name" value="{{ $prospect->contact_name }}"><input type="hidden" name="email" value="{{ $prospect->email }}"><input type="hidden" name="website_url" value="{{ $prospect->website_url }}"><input type="hidden" name="status" value="{{ $prospect->status }}">
                    <div><label for="outreach_subject" class="ui-label">Subject</label><input id="outreach_subject" name="outreach_subject" value="{{ old('outreach_subject', $prospect->outreach_subject) }}" class="ui-input mt-1 w-full" placeholder="Waiting for research…"></div>
                    <div><label for="outreach_body" class="ui-label">Message</label><textarea id="outreach_body" name="outreach_body" rows="8" class="ui-input mt-1 w-full" placeholder="Waiting for research…">{{ old('outreach_body', $prospect->outreach_body) }}</textarea></div>
                    <div><label for="showcase_video_url" class="ui-label">Video URL (optional)</label><input id="showcase_video_url" type="url" name="showcase_video_url" value="{{ old('showcase_video_url', $prospect->showcase_video_url) }}" placeholder="https://www.loom.com/share/..." class="ui-input mt-1 w-full"><p class="mt-1 text-slate-500 text-base sm:text-sm">This prospect-specific link appears behind the video button in test and live emails.</p>@error('showcase_video_url')<p class="mt-1 text-red-600 text-base sm:text-sm">{{ $message }}</p>@enderror</div>
                    <input type="hidden" name="include_site_audit" value="0">
                    <div>
                        <label for="include_site_audit" class="flex items-center gap-2 text-base font-medium sm:text-sm">
                            <x-prospect-checkbox id="include_site_audit" name="include_site_audit" :checked="(bool) old('include_site_audit', $prospect->include_site_audit)" />
                            Include site audit
                        </label>
                        <p class="mt-1 text-base text-slate-500 sm:text-sm">Adds the site audit after the video in test and live emails. Save the draft before sending a test. The audit needs a website URL and completed research.</p>
                        @error('include_site_audit')<p class="mt-1 text-sm text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <button type="submit" class="ui-button {{ $needsPersonalisedVideo ? 'ui-button-secondary' : 'ui-button-primary' }}">Save draft<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button>
                </form>
        <div class="mt-6 flex flex-wrap gap-2 border-t border-slate-950/10 pt-5">
            @if ($prospect->outreach_subject && $prospect->outreach_body)
                <form method="POST" action="{{ route('admin.prospects.test-email', $prospect) }}">@csrf<button type="submit" class="ui-button relative ui-button-secondary">Send test to {{ Auth::user()->email }}<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button></form>
            @endif
            @if ($prospect->outreach_body && ! $prospect->approved_at)
                <form method="POST" action="{{ route('admin.prospects.approve', $prospect) }}">@csrf<button type="submit" class="ui-button relative ui-button-secondary">Approve draft<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button></form>
            @endif
            @if ($prospect->approved_at && (! $prospect->sent_at || $prospect->isOutreachFollowUpDue()))
                @if ($prospect->sent_at)<p class="w-full text-base text-slate-500 sm:text-sm">Manual resend of the saved initial draft. This is separate from the automatic follow-up shown in the schedule.</p>@endif
                <form method="POST" action="{{ route('admin.prospects.send', $prospect) }}">@csrf<button type="submit" class="ui-button relative ui-button-secondary">Send approved email<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button></form>
            @endif
        </div>
        @if ($prospect->approved_at && ! $prospect->sent_at)
            <form method="POST" action="{{ route('admin.prospects.schedule', $prospect) }}" class="mt-4 flex flex-wrap items-end gap-3">
                @csrf
                <div class="min-w-0 flex-1"><label for="scheduled_send_at" class="ui-label">Schedule initial email (UK time)</label><input id="scheduled_send_at" type="datetime-local" name="scheduled_send_at" value="{{ old('scheduled_send_at', $prospect->scheduled_send_at?->setTimezone('Europe/London')->format('Y-m-d\TH:i')) }}" min="{{ now('Europe/London')->addMinute()->format('Y-m-d\TH:i') }}" required class="ui-input mt-1 w-full"></div>
                <button type="submit" class="ui-button relative ui-button-secondary">{{ $prospect->scheduled_send_at ? 'Reschedule email' : 'Schedule email' }}<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button>
            </form>
        @endif
    </div>
</details>
