@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-start justify-between gap-4"><div><a href="{{ route('admin.prospects.index') }}" class="text-sm font-medium text-slate-600">← Outreach</a><div class="mt-3 flex flex-wrap items-center gap-3"><h1 class="text-3xl font-semibold tracking-tight">{{ $prospect->business_name }}</h1><span class="rounded-full bg-slate-200 px-2.5 py-1 text-xs font-semibold text-slate-700">{{ str($prospect->status)->replace('_', ' ')->title() }}</span>@unless ($prospect->website_url)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">Website opportunity</span>@endunless</div>@if ($prospect->website_url)<a href="{{ $prospect->website_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 inline-flex text-sm text-teal-700 hover:underline">{{ $prospect->website_url }} ↗</a>@else<p class="mt-1 text-sm text-slate-500">No website was linked from the public listing.</p>@endif</div>
        <div class="flex flex-wrap gap-2">
            @if ($prospect->website_url && ! in_array($prospect->analysis_status, ['pending', 'running']))<form method="POST" action="{{ route('admin.prospects.analyse', $prospect) }}">@csrf<button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm font-medium">Research again</button></form>@endif
            @if ($prospect->outreach_body && ! $prospect->approved_at)<form method="POST" action="{{ route('admin.prospects.approve', $prospect) }}">@csrf<button class="rounded-lg bg-teal-600 px-3 py-2 text-sm font-semibold text-white hover:bg-teal-700">Approve draft</button></form>@endif
            @if ($prospect->approved_at && ! $prospect->sent_at)<form method="POST" action="{{ route('admin.prospects.send', $prospect) }}">@csrf<button class="rounded-lg bg-slate-950 px-3 py-2 text-sm font-semibold text-white">Send approved email</button></form>@endif
            <form method="POST" action="{{ route('admin.prospects.destroy', $prospect) }}" data-confirm-action-form>
                @csrf
                @method('DELETE')
                <button type="button" data-confirm-action data-confirm-title="Delete this prospect?" data-confirm-message="The prospect, website research, outreach draft, and activity history will be permanently deleted. This cannot be undone." data-confirm-label="Delete prospect" data-confirm-danger class="rounded-lg border border-red-300 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Delete</button>
            </form>
        </div>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($prospect->analysis_status === 'failed')<div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800"><strong>Research failed:</strong> {{ $prospect->analysis_error }}</div>@endif
    <div class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
        <div class="space-y-6">
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-4"><div><h2 class="font-semibold">Website opportunities</h2><p class="text-sm text-slate-500">Verified findings used to personalise the draft.</p></div>@if ($prospect->opportunity_score !== null)<div class="rounded-xl bg-amber-50 px-4 py-2 text-center"><p class="text-2xl font-semibold text-amber-800">{{ $prospect->opportunity_score }}</p><p class="text-[10px] font-semibold uppercase tracking-wide text-amber-700">Opportunity</p></div>@endif</div>
                <div class="mt-4 space-y-3">@if (! $prospect->website_url)<div class="rounded-lg bg-violet-50 p-4 text-sm text-violet-800">Website audit skipped. This prospect is being treated as an opportunity for a new website.</div>@elseif (in_array($prospect->analysis_status, ['pending', 'running']))<div class="rounded-lg bg-slate-50 p-4 text-sm text-slate-600">Website research is {{ $prospect->analysis_status }}. The draft will appear here automatically when the queue worker finishes.</div>@else @forelse ($prospect->findings ?? [] as $finding)<div class="rounded-lg border border-slate-200 p-3"><div class="flex items-center gap-2"><span class="size-2 rounded-full {{ $finding['severity'] === 'failed' ? 'bg-red-500' : 'bg-amber-400' }}"></span><p class="text-sm font-semibold">{{ $finding['title'] }}</p></div><p class="mt-1 text-sm text-slate-600">{{ $finding['message'] }}</p></div>@empty<div class="rounded-lg bg-emerald-50 p-4 text-sm text-emerald-800">No clear homepage issues were found. Review the general introduction carefully before approving it.</div>@endforelse @endif</div>
            </section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><div class="flex items-center justify-between gap-3"><div><h2 class="font-semibold">Outreach draft</h2><p class="text-sm text-slate-500">Editing an approved draft resets its approval.</p></div>@if ($prospect->approved_at)<span class="rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Approved</span>@endif</div>
                <form method="POST" action="{{ route('admin.prospects.update', $prospect) }}" class="mt-4 space-y-4">@csrf @method('PUT')
                    <input type="hidden" name="business_name" value="{{ $prospect->business_name }}"><input type="hidden" name="contact_name" value="{{ $prospect->contact_name }}"><input type="hidden" name="email" value="{{ $prospect->email }}"><input type="hidden" name="website_url" value="{{ $prospect->website_url }}"><input type="hidden" name="status" value="{{ $prospect->status }}">
                    <div><label for="outreach_subject" class="text-sm font-medium">Subject</label><input id="outreach_subject" name="outreach_subject" value="{{ old('outreach_subject', $prospect->outreach_subject) }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Waiting for research…"></div>
                    <div><label for="outreach_body" class="text-sm font-medium">Message</label><textarea id="outreach_body" name="outreach_body" rows="11" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm" placeholder="Waiting for research…">{{ old('outreach_body', $prospect->outreach_body) }}</textarea></div>
                    <button class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-semibold">Save draft</button>
                </form>
            </section>
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
                <input type="hidden" name="outreach_subject" value="{{ $prospect->outreach_subject }}"><input type="hidden" name="outreach_body" value="{{ $prospect->outreach_body }}">
                <div><label class="text-sm font-medium">Business</label><input name="business_name" value="{{ $prospect->business_name }}" required class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label class="text-sm font-medium">Contact</label><input name="contact_name" value="{{ $prospect->contact_name }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label class="text-sm font-medium">Email</label><input type="email" name="email" value="{{ $prospect->email }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div><div><label class="text-sm font-medium">Website <span class="font-normal text-slate-500">(optional)</span></label><input type="url" name="website_url" value="{{ $prospect->website_url }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div>
                <div class="grid gap-4 sm:grid-cols-2"><div><label class="text-sm font-medium">Stage</label><select name="status" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">@foreach (\App\Models\Prospect::STATUSES as $status)<option value="{{ $status }}" @selected($prospect->status === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select></div><div><label class="text-sm font-medium">Next follow-up</label><input type="datetime-local" name="next_follow_up_at" value="{{ $prospect->next_follow_up_at?->format('Y-m-d\\TH:i') }}" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></div></div>
                <div><label class="text-sm font-medium">Notes</label><textarea name="notes" rows="4" class="mt-1 w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ $prospect->notes }}</textarea></div><label class="flex items-center gap-2 text-sm"><input type="checkbox" name="suppressed" value="1" @checked($prospect->suppressed_at) class="rounded border-slate-300">Never send email to this prospect</label><button class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white">Save prospect</button>
            </form></section>
            <section class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm"><h2 class="font-semibold">Activity</h2><ol class="mt-4 space-y-4">@forelse ($prospect->activities as $activity)<li class="flex gap-3"><span class="mt-1.5 size-2 shrink-0 rounded-full bg-slate-400"></span><div><p class="text-sm">{{ $activity->description }}</p><p class="text-xs text-slate-500">{{ $activity->user?->name ?: 'System' }} · {{ $activity->created_at->diffForHumans() }}</p></div></li>@empty<li class="text-sm text-slate-500">No activity yet.</li>@endforelse</ol></section>
        </div>
    </div>

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
