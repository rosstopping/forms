@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Lead generation</p><h1 class="text-3xl font-semibold tracking-tight">Outreach</h1><p class="mt-1 text-sm text-slate-600">Research prospects, review evidence-based drafts, and track every conversation.</p></div>
        <div class="flex items-center gap-3"><a href="{{ route('admin.prospect-discoveries.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Find prospects</a><a href="{{ route('admin.prospects.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Add prospect</a></div>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    <nav aria-label="Outreach views" class="flex gap-1 overflow-x-auto border-b border-slate-200">
        @foreach (['dashboard' => 'Dashboard', 'hot' => 'Hot Leads', 'warm' => 'Warm Leads'] as $tab => $label)
            <a href="{{ $tab === 'dashboard' ? route('admin.prospects.index') : route('admin.prospects.index', ['tab' => $tab]) }}" @class(['whitespace-nowrap border-b-2 px-4 py-3 text-sm font-semibold', 'border-teal-600 text-teal-700' => $activeTab === $tab, 'border-transparent text-slate-500 hover:border-slate-300 hover:text-slate-800' => $activeTab !== $tab]) @if ($activeTab === $tab) aria-current="page" @endif>{{ $label }}@if ($tab === 'hot') <span class="ml-1 rounded-full bg-red-100 px-2 py-0.5 text-xs text-red-800">{{ $temperatureSummary['hot'] ?? 0 }}</span>@elseif ($tab === 'warm') <span class="ml-1 rounded-full bg-orange-100 px-2 py-0.5 text-xs text-orange-800">{{ $temperatureSummary['warm'] ?? 0 }}</span>@endif</a>
        @endforeach
    </nav>
    @if ($activeTab === 'dashboard' && ! request()->hasAny(['status', 'email_status', 'search']))
    <section class="rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
        <div><p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Daily command centre</p><h2 class="mt-1 text-xl font-semibold text-slate-950">Today’s priorities</h2></div>
        <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
            <a href="{{ route('admin.prospects.index', ['tab' => 'hot']) }}" class="rounded-xl bg-red-50 p-4 text-sm font-medium text-red-900 hover:bg-red-100">🔥 <strong>{{ $hotVideoProspectsCount }}</strong> hot {{ str('lead')->plural($hotVideoProspectsCount) }} need personalised videos</a>
            <a href="{{ route('admin.prospects.index', ['tab' => 'warm']) }}" class="rounded-xl bg-orange-50 p-4 text-sm font-medium text-orange-900 hover:bg-orange-100">🟠 <strong>{{ $priorityCounts['warm'] }}</strong> warm {{ str('lead')->plural($priorityCounts['warm']) }} are engaging</a>
            <a href="#recent-replies" class="rounded-xl bg-emerald-50 p-4 text-sm font-medium text-emerald-900 hover:bg-emerald-100">💬 <strong>{{ $priorityCounts['replied'] }}</strong> {{ str('prospect')->plural($priorityCounts['replied']) }} replied today</a>
            <div class="rounded-xl bg-violet-50 p-4 text-sm font-medium text-violet-900">📅 <strong>{{ $priorityCounts['booking'] }}</strong> {{ str('prospect')->plural($priorityCounts['booking']) }} visited the booking page today</div>
            <div class="rounded-xl bg-sky-50 p-4 text-sm font-medium text-sky-900">🧊 <strong>{{ $priorityCounts['cold_followed_up'] }}</strong> cold {{ str('prospect')->plural($priorityCounts['cold_followed_up']) }} automatically followed up today</div>
            <div class="rounded-xl bg-slate-100 p-4 text-sm font-medium text-slate-800">💤 <strong>{{ $priorityCounts['nurtured'] }}</strong> {{ str('prospect')->plural($priorityCounts['nurtured']) }} moved to nurture today</div>
        </div>
    </section>
    @endif
    @if ($activeTab === 'hot' && ! request()->hasAny(['status', 'email_status', 'search']))
    @if ($hotVideoProspects->isNotEmpty())
        <section id="needs-personalised-video" class="scroll-mt-6 rounded-2xl border border-red-200 bg-red-50/70 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-red-700">Manual action queue</p><h2 class="mt-1 text-xl font-semibold text-red-950">Needs Personalised Video</h2><p class="mt-1 text-sm text-red-800">These prospects crossed the hot threshold. Their automated cold sequence is paused.</p></div>
                <span class="rounded-full bg-red-600 px-3 py-1 text-sm font-semibold text-white">{{ $hotVideoProspectsCount }} {{ str('lead')->plural($hotVideoProspectsCount) }}</span>
            </div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach ($hotVideoProspects as $hotProspect)
                    @php($auditLink = $hotProspect->outreachDeliveries->flatMap->links->firstWhere('kind', 'website_audit'))
                    <article class="rounded-xl border border-red-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4">
                            <div class="min-w-0"><h3 class="truncate font-semibold text-slate-950">{{ $hotProspect->business_name }}</h3><p class="truncate text-sm text-slate-500">{{ parse_url($hotProspect->website_url, PHP_URL_HOST) ?: $hotProspect->website_url }}</p></div>
                            <span class="shrink-0 rounded-full bg-red-100 px-2.5 py-1 text-xs font-bold text-red-800">Score {{ $hotProspect->outreachState->engagement_score }}</span>
                        </div>
                        <div class="mt-3 space-y-1.5">
                            @foreach ($hotProspect->engagementEvents as $event)
                                <p class="flex items-center justify-between gap-3 text-xs text-slate-600"><span>{{ $event->event_type->label() }} <span class="font-semibold text-emerald-700">+{{ $event->score_delta }}</span></span><span class="shrink-0 text-slate-400">{{ $event->occurred_at->diffForHumans() }}</span></p>
                            @endforeach
                        </div>
                        <div class="mt-4 flex flex-wrap items-center gap-2">
                            <a href="{{ route('admin.prospects.show', $hotProspect) }}#personalised-video" class="rounded-lg bg-red-600 px-3 py-2 text-sm font-semibold text-white hover:bg-red-700">Add Personalised Video</a>
                            @if ($auditLink)<a href="{{ $auditLink->destination_url }}" target="_blank" rel="noopener noreferrer" class="rounded-lg border border-slate-300 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">View audit ↗</a>@endif
                            <span class="text-xs text-slate-500">Initial email {{ $hotProspect->outreachState->initial_email_sent_at?->diffForHumans() ?? 'not recorded' }}</span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
    @endif
    @if ($activeTab === 'warm' && ! request()->hasAny(['status', 'email_status', 'search']))
    @if ($warmProspects->isNotEmpty())
        <section id="warm-leads" class="scroll-mt-6 rounded-2xl border border-orange-200 bg-orange-50/70 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3"><div><p class="text-xs font-semibold uppercase tracking-wider text-orange-700">Recent intent</p><h2 class="mt-1 text-xl font-semibold text-orange-950">Warm Leads</h2><p class="mt-1 text-sm text-orange-800">Sorted by most recent engagement. Monitor these without crowding them.</p></div><span class="rounded-full bg-orange-600 px-3 py-1 text-sm font-semibold text-white">{{ $priorityCounts['warm'] }}</span></div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach ($warmProspects as $warmProspect)
                    <article class="rounded-xl border border-orange-100 bg-white p-4 shadow-sm"><div class="flex items-start justify-between gap-4"><div class="min-w-0"><h3 class="truncate font-semibold text-slate-950">{{ $warmProspect->business_name }}</h3><p class="truncate text-sm text-slate-500">{{ parse_url($warmProspect->website_url, PHP_URL_HOST) ?: $warmProspect->email }}</p></div><span class="shrink-0 rounded-full bg-orange-100 px-2.5 py-1 text-xs font-bold text-orange-800">Score {{ $warmProspect->outreachState->engagement_score }}</span></div><div class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600"><span class="font-medium text-slate-700">Email sent</span> {{ $warmProspect->outreachState->initial_email_sent_at?->setTimezone('Europe/London')->format('j M Y, H:i') ?? 'Not recorded' }}</div><div class="mt-3 space-y-1.5">@foreach ($warmProspect->engagementEvents as $event)<p class="flex items-center justify-between gap-3 text-xs text-slate-600"><span>{{ $event->event_type->label() }} <span class="font-semibold text-emerald-700">+{{ $event->score_delta }}</span></span><span class="shrink-0 text-slate-400">{{ $event->occurred_at->setTimezone('Europe/London')->format('j M Y, H:i') }}</span></p>@endforeach</div><a href="{{ route('admin.prospects.show', $warmProspect) }}" class="mt-4 inline-flex rounded-lg border border-orange-300 px-3 py-2 text-sm font-semibold text-orange-800 hover:bg-orange-50">Review activity</a></article>
                @endforeach
            </div>
        </section>
    @endif
    @endif
    @if ($activeTab === 'dashboard' && ! request()->hasAny(['status', 'email_status', 'search']))
    @if ($recentReplies->isNotEmpty())
        <section id="recent-replies" class="scroll-mt-6 rounded-2xl border border-emerald-200 bg-emerald-50/70 p-5 shadow-sm">
            <div><p class="text-xs font-semibold uppercase tracking-wider text-emerald-700">Conversation required</p><h2 class="mt-1 text-xl font-semibold text-emerald-950">Recent Replies</h2><p class="mt-1 text-sm text-emerald-800">Automation is stopped for these prospects.</p></div>
            <div class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">@foreach ($recentReplies as $reply)<a href="{{ route('admin.prospects.show', $reply) }}" class="rounded-xl border border-emerald-100 bg-white p-4 shadow-sm hover:border-emerald-300"><p class="font-semibold text-slate-950">{{ $reply->business_name }}</p><p class="mt-1 text-sm text-slate-500">Replied {{ $reply->replied_at?->diffForHumans() ?? $reply->outreachState->stopped_at?->diffForHumans() }}</p></a>@endforeach</div>
        </section>
    @endif
    @if ($manualFollowUpProspects->isNotEmpty())
        <section class="rounded-2xl border border-violet-200 bg-violet-50/70 p-5 shadow-sm">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div><p class="text-xs font-semibold uppercase tracking-wider text-violet-700">Personal attention recommended</p><h2 class="mt-1 text-xl font-semibold text-violet-950">Manual Follow-up Recommended</h2><p class="mt-1 text-sm text-violet-800">These prospects kept engaging after their personalised video. No more automatic email will be sent.</p></div>
                <span class="rounded-full bg-violet-600 px-3 py-1 text-sm font-semibold text-white">{{ $manualFollowUpProspectsCount }} {{ str('lead')->plural($manualFollowUpProspectsCount) }}</span>
            </div>
            <div class="mt-5 grid gap-4 lg:grid-cols-2">
                @foreach ($manualFollowUpProspects as $manualProspect)
                    <article class="rounded-xl border border-violet-100 bg-white p-4 shadow-sm">
                        <div class="flex items-start justify-between gap-4"><div class="min-w-0"><h3 class="truncate font-semibold text-slate-950">{{ $manualProspect->business_name }}</h3><p class="truncate text-sm text-slate-500">{{ parse_url($manualProspect->website_url, PHP_URL_HOST) ?: $manualProspect->email }}</p></div><span class="shrink-0 rounded-full bg-violet-100 px-2.5 py-1 text-xs font-bold text-violet-800">Score {{ $manualProspect->outreachState->engagement_score }}</span></div>
                        <ul class="mt-3 space-y-1.5">
                            @foreach ($manualProspect->outreachState->manual_follow_up_reason ?? [] as $reason)
                                <li class="flex items-center justify-between gap-3 text-xs text-slate-600"><span>{{ $reason['label'] }}</span><span class="shrink-0 text-slate-400">{{ \Carbon\CarbonImmutable::parse($reason['last_at'])->diffForHumans() }}</span></li>
                            @endforeach
                        </ul>
                        <div class="mt-4 flex flex-wrap items-center gap-2"><a href="{{ route('admin.prospects.show', $manualProspect) }}" class="rounded-lg bg-violet-600 px-3 py-2 text-sm font-semibold text-white hover:bg-violet-700">Review and follow up</a><span class="text-xs text-slate-500">Video sent {{ $manualProspect->outreachState->video_sent_at?->diffForHumans() }}</span></div>
                    </article>
                @endforeach
            </div>
        </section>
    @endif
    @endif
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('admin.prospects.index', ['tab' => 'hot']) }}" class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm hover:border-red-400"><p class="text-xs font-semibold uppercase tracking-wide text-red-700">Hot leads</p><p class="mt-2 text-2xl font-semibold text-red-950">{{ $temperatureSummary['hot'] ?? 0 }}</p></a>
        @foreach (['new' => 'New', 'drafted' => 'Ready to review', 'contacted' => 'Contacted', 'replied' => 'Replied'] as $value => $label)
            <a href="{{ route('admin.prospects.index', ['status' => $value]) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-slate-400"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $summary[$value] ?? 0 }}</p></a>
        @endforeach
    </div>
    <form method="GET" class="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        @if ($activeTab !== 'dashboard')<input type="hidden" name="tab" value="{{ $activeTab }}">@endif
        <input name="search" value="{{ request('search') }}" placeholder="Search businesses or emails, separated by commas" aria-label="Search businesses or emails, separated by commas" class="min-w-64 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All stages</option>@foreach (\App\Models\Prospect::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select>
        <select name="email_status" aria-label="Email address" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All email statuses</option><option value="missing" @selected(request('email_status') === 'missing')>Without email address</option><option value="present" @selected(request('email_status') === 'present')>With email address</option></select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>
    <form method="POST" action="{{ route('admin.prospects.bulk') }}" data-bulk-prospects-form data-bulk-prospects-total="{{ $matchingProspectsCount }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @csrf
        <input type="hidden" name="selection_scope" value="page" data-bulk-prospects-scope>
        <input type="hidden" name="tab" value="{{ $activeTab }}">
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="temperature" value="{{ $activeTab === 'dashboard' ? 'cold' : $activeTab }}">
        <input type="hidden" name="email_status" value="{{ request('email_status') }}">
        <div class="flex flex-col gap-3 border-b border-slate-200 bg-slate-50 p-3 lg:flex-row lg:items-center">
            <div class="flex items-center gap-3">
                <input type="checkbox" data-bulk-prospects-select-page aria-label="Select all prospects on this page" class="size-5 rounded border-slate-300">
                <span class="text-sm text-slate-600"><span data-bulk-prospects-count>0</span> selected</span>
                @if ($matchingProspectsCount > $prospects->count())
                    <button type="button" data-bulk-prospects-select-all class="hidden text-sm font-semibold text-teal-700 hover:underline">Select all {{ $matchingProspectsCount }} matching prospects</button>
                @endif
                <button type="button" data-bulk-prospects-clear class="hidden text-sm font-medium text-slate-600 hover:underline">Clear</button>
            </div>
            <div class="flex flex-1 flex-wrap items-center gap-2 lg:justify-end">
                <label for="bulk_prospect_action" class="sr-only">Bulk action</label>
                <select id="bulk_prospect_action" name="action" data-bulk-prospects-action disabled class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm disabled:cursor-not-allowed disabled:opacity-50">
                    <option value="">Choose an action</option>
                    <option value="approve">Approve Draft</option>
                    <option value="research_again">Research Again</option>
                    <option value="schedule_approved_email">Schedule Approved Email</option>
                    <option value="cancel_scheduled_email">Cancel Scheduled Send</option>
                    <option value="mark_as_draft">Mark as Draft Again</option>
                    <option value="send_approved_email">Send Approved Email</option>
                    <option value="delete">Delete</option>
                </select>
                <div data-bulk-prospects-schedule class="hidden">
                    <label for="bulk_scheduled_send_at" class="sr-only">Send date and time (UK time)</label>
                    <input id="bulk_scheduled_send_at" type="datetime-local" name="scheduled_send_at" min="{{ now('Europe/London')->addMinute()->format('Y-m-d\TH:i') }}" class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm">
                </div>
                <button data-bulk-prospects-apply disabled class="rounded-lg bg-slate-950 px-4 py-2 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:opacity-40">Apply</button>
            </div>
        </div>
        @error('search')<p class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">The bulk action was not run: {{ $message }}</p>@enderror
        @error('action')<p class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">The bulk action was not run: {{ $message }}</p>@enderror
        @error('prospect_ids')<p class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</p>@enderror
        @error('scheduled_send_at')<p class="border-b border-red-200 bg-red-50 px-4 py-2 text-sm text-red-700">{{ $message }}</p>@enderror
        <div class="divide-y divide-slate-100">
            @forelse ($prospects as $prospect)
                <div class="flex items-center gap-3 p-4 hover:bg-slate-50">
                    <input type="checkbox" name="prospect_ids[]" value="{{ $prospect->id }}" aria-label="Select {{ $prospect->business_name }}" data-bulk-prospects-checkbox class="size-5 shrink-0 rounded border-slate-300">
                    <a href="{{ route('admin.prospects.show', $prospect) }}" class="grid min-w-0 flex-1 gap-3 md:grid-cols-[2fr_1fr_1fr_auto] md:items-center">
                        <div><p class="font-semibold text-slate-900">{{ $prospect->business_name }}</p><p class="text-sm text-slate-500">{{ $prospect->contact_name ?: 'No contact' }} · {{ $prospect->email ?: 'No email yet' }}</p></div>
                        <div class="text-sm text-slate-600">@if ($prospect->website_url){{ parse_url($prospect->website_url, PHP_URL_HOST) }}@else<span class="font-medium text-violet-700">Website opportunity</span>@endif</div>
                        <div class="flex flex-wrap gap-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($prospect->status)->replace('_', ' ')->title() }}</span><span @class(['rounded-full px-2.5 py-1 text-xs font-semibold', 'bg-red-100 text-red-800' => $prospect->lead_temperature === 'hot', 'bg-orange-100 text-orange-800' => $prospect->lead_temperature === 'warm', 'bg-sky-100 text-sky-800' => $prospect->lead_temperature === 'cold'])>{{ str($prospect->lead_temperature)->title() }}</span>@if ($prospect->scheduled_send_at)<span class="rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">Scheduled {{ $prospect->scheduled_send_at->setTimezone('Europe/London')->format('j M, H:i') }}</span>@endif</div>
                        <div class="text-right">@if ($prospect->opportunity_score !== null)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $prospect->opportunity_score }} opportunity</span>@else<span class="text-xs text-slate-500">{{ str($prospect->analysis_status)->title() }}</span>@endif</div>
                    </a>
                </div>
            @empty
                <div class="p-10 text-center"><p class="font-medium">No prospects yet</p><p class="mt-1 text-sm text-slate-500">Add a business and Sitewell will research its website automatically.</p></div>
            @endforelse
        </div>
    </form>
    {{ $prospects->links() }}
</div>
@endsection
