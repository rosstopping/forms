@unless ($prospect->isAgencyPartner())
            <section class="ui-panel ui-section">
                <div><h2 class="font-semibold">Public contact details</h2><p class="text-slate-500 text-base sm:text-sm">Published details found on the business website. Check the linked source before using them.</p></div>
                @if ($prospect->analysis_status === 'pending' || $prospect->analysis_status === 'running')
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-slate-600 text-base sm:text-sm">Contact discovery runs with the website research.</p>
                @elseif (filled(data_get($prospect->contact_details, 'emails')) || filled(data_get($prospect->contact_details, 'phones')) || filled(data_get($prospect->contact_details, 'addresses')) || data_get($prospect->contact_details, 'contact_form_url'))
                    <dl class="mt-4 space-y-3 text-sm">
                        @foreach (data_get($prospect->contact_details, 'emails', []) as $email)
                            <div><dt class="text-base sm:text-sm font-semibold  text-slate-500">Published email</dt><dd class="mt-1"><a href="mailto:{{ $email['value'] }}" class="font-medium text-teal-700 hover:underline">{{ $email['value'] }}</a><a href="{{ $email['source_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-base sm:text-sm text-slate-500 hover:underline">View source ↗</a></dd></div>
                        @endforeach
                        @foreach (data_get($prospect->contact_details, 'phones', []) as $phone)
                            <div><dt class="text-base sm:text-sm font-semibold  text-slate-500">Published phone</dt><dd class="mt-1"><a href="tel:{{ $phone['value'] }}" class="font-medium text-teal-700 hover:underline">{{ $phone['value'] }}</a><a href="{{ $phone['source_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-base sm:text-sm text-slate-500 hover:underline">View source ↗</a></dd></div>
                        @endforeach
                        @foreach (data_get($prospect->contact_details, 'addresses', []) as $address)
                            <div><dt class="text-base sm:text-sm font-semibold  text-slate-500">Published address</dt><dd class="mt-1"><span class="font-medium text-slate-800">{{ $address['value'] }}</span><a href="{{ $address['source_url'] }}" target="_blank" rel="noopener noreferrer" class="ml-2 text-base sm:text-sm text-slate-500 hover:underline">View source ↗</a></dd></div>
                        @endforeach
                        @if (data_get($prospect->contact_details, 'contact_form_url'))
                            <div><dt class="text-base sm:text-sm font-semibold  text-slate-500">Contact form</dt><dd class="mt-1"><a href="{{ data_get($prospect->contact_details, 'contact_form_url') }}" target="_blank" rel="noopener noreferrer" class="font-medium text-teal-700 hover:underline">Open contact form ↗</a></dd></div>
                        @endif
                    </dl>
                @else
                    <p class="mt-4 rounded-lg bg-slate-50 p-4 text-slate-600 text-base sm:text-sm">No clearly published email address, phone number, or contact form was found.</p>
                @endif
            </section>
@endunless
            <section class="ui-panel ui-section"><h2 class="font-semibold">Prospect details</h2><form method="POST" action="{{ route('admin.prospects.update', $prospect) }}" class="mt-4 space-y-4">@csrf @method('PUT')
                <input type="hidden" name="outreach_subject" value="{{ $prospect->outreach_subject }}"><input type="hidden" name="outreach_body" value="{{ $prospect->outreach_body }}"><input type="hidden" name="showcase_video_url" value="{{ $prospect->showcase_video_url }}">
                <div><label for="prospect_business_name" class="ui-label">Business</label><input id="prospect_business_name" name="business_name" value="{{ $prospect->business_name }}" required class="ui-input mt-1 w-full"></div><div><label for="prospect_contact_name" class="ui-label">Contact</label><input id="prospect_contact_name" name="contact_name" value="{{ $prospect->contact_name }}" class="ui-input mt-1 w-full"></div><div><label for="prospect_email" class="ui-label">Email</label><input type="email" id="prospect_email" name="email" value="{{ $prospect->email }}" class="ui-input mt-1 w-full"></div><div><label for="prospect_website_url" class="ui-label">Website <span class="font-normal text-slate-500">(optional)</span></label><input type="url" id="prospect_website_url" name="website_url" value="{{ $prospect->website_url }}" class="ui-input mt-1 w-full"></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label for="prospect_status" class="ui-label">Stage</label><select id="prospect_status" name="status" class="ui-input mt-1 w-full">@foreach (\App\Models\Prospect::STATUSES as $status)<option value="{{ $status }}" @selected($prospect->status === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div><div><label for="prospect_next_follow_up_at" class="ui-label">Follow-up reference date (UK)</label><input type="datetime-local" id="prospect_next_follow_up_at" name="next_follow_up_at" value="{{ $prospect->next_follow_up_at?->setTimezone('Europe/London')->format('Y-m-d\TH:i') }}" class="ui-input mt-1 w-full"></div></div>
                <p class="text-base text-slate-500 sm:text-sm">The reference date does not reschedule automatic emails. See Emails &amp; schedule for actual send times.</p><div><label for="prospect_notes" class="ui-label">Notes</label><textarea id="prospect_notes" name="notes" rows="4" class="ui-input mt-1 w-full">{{ $prospect->notes }}</textarea></div><label for="prospect_suppressed" class="ui-label flex items-center gap-2"><x-prospect-checkbox id="prospect_suppressed" name="suppressed" :checked="(bool) $prospect->suppressed_at" />Never send email to this prospect</label><button type="submit" class="ui-button relative ui-button-secondary">Save prospect<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button>
            </form></section>
    @unless ($prospect->isAgencyPartner())
    <details class="ui-panel group">
        <summary class="flex cursor-pointer list-none items-center justify-between gap-4 p-5 marker:content-none">
            <div><h2 class="font-semibold text-slate-900">Website opportunities</h2><p class="mt-1 text-slate-500 text-base sm:text-sm">Verified website findings used to personalise the outreach draft.</p></div>
            <div class="flex shrink-0 items-center gap-3">@if ($prospect->opportunity_score !== null)<div class="rounded-lg bg-amber-50 px-3 py-1.5 text-center"><p class="font-semibold text-amber-800">{{ $prospect->opportunity_score }}</p><p class="text-[9px] font-semibold  text-amber-700">Opportunity</p></div>@endif<span class="grid size-8 place-items-center rounded-full bg-slate-100 text-slate-500 transition group-open:rotate-180" aria-hidden="true">⌄</span></div>
        </summary>
        <div class="space-y-3 border-t border-slate-950/10 p-5">@if (! $prospect->website_url)<div class="rounded-lg bg-violet-50 p-4 text-sm text-slate-600">Website audit skipped. This prospect is being treated as an opportunity for a new website.</div>@elseif (in_array($prospect->analysis_status, ['pending', 'running']))<div class="ui-well p-4 text-sm text-slate-600">Website research is {{ $prospect->analysis_status }}. The draft will appear here automatically when the queue worker finishes.</div>@else @forelse ($prospect->findings ?? [] as $finding)<div class="rounded-lg border border-slate-950/10 p-3"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $finding['severity'] === 'failed' ? 'bg-white0' : 'bg-amber-400' }}"></span><p class="font-semibold text-base sm:text-sm">{{ $finding['title'] }}</p></div><p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $finding['message'] }}</p></div>@empty<div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">No clear homepage issues were found. Review the general introduction carefully before approving it.</div>@endforelse @endif</div>
    </details>


@endunless
