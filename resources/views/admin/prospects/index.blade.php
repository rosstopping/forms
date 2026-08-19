@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Lead generation</p><h1 class="text-3xl font-semibold tracking-tight">Outreach</h1><p class="mt-1 text-sm text-slate-600">Research prospects, review evidence-based drafts, and track every conversation.</p></div>
        <div class="flex items-center gap-3"><a href="{{ route('admin.prospect-discoveries.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Find prospects</a><a href="{{ route('admin.prospects.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Add prospect</a></div>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-5">
        <a href="{{ route('admin.prospects.index', ['temperature' => 'hot']) }}" class="rounded-xl border border-red-200 bg-red-50 p-4 shadow-sm hover:border-red-400"><p class="text-xs font-semibold uppercase tracking-wide text-red-700">Hot leads</p><p class="mt-2 text-2xl font-semibold text-red-950">{{ $temperatureSummary['hot'] ?? 0 }}</p></a>
        @foreach (['new' => 'New', 'drafted' => 'Ready to review', 'contacted' => 'Contacted', 'replied' => 'Replied'] as $value => $label)
            <a href="{{ route('admin.prospects.index', ['status' => $value]) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-slate-400"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $summary[$value] ?? 0 }}</p></a>
        @endforeach
    </div>
    <form method="GET" class="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <input name="search" value="{{ request('search') }}" placeholder="Search business or email" class="min-w-64 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All stages</option>@foreach (\App\Models\Prospect::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select>
        <select name="temperature" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All engagement</option>@foreach (\App\Models\Prospect::LEAD_TEMPERATURES as $temperature)<option value="{{ $temperature }}" @selected(request('temperature') === $temperature)>{{ str($temperature)->title() }}</option>@endforeach</select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>
    <form method="POST" action="{{ route('admin.prospects.bulk') }}" data-bulk-prospects-form data-bulk-prospects-total="{{ $matchingProspectsCount }}" class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        @csrf
        <input type="hidden" name="selection_scope" value="page" data-bulk-prospects-scope>
        <input type="hidden" name="search" value="{{ request('search') }}">
        <input type="hidden" name="status" value="{{ request('status') }}">
        <input type="hidden" name="temperature" value="{{ request('temperature') }}">
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
