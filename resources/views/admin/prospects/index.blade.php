@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div><p class="text-sm font-medium text-teal-700">Lead generation</p><h1 class="text-3xl font-semibold tracking-tight">Outreach</h1><p class="mt-1 text-sm text-slate-600">Research prospects, review evidence-based drafts, and track every conversation.</p></div>
        <div class="flex items-center gap-3"><a href="{{ route('admin.prospect-discoveries.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Find prospects</a><a href="{{ route('admin.prospects.create') }}" class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Add prospect</a></div>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        @foreach (['new' => 'New', 'drafted' => 'Ready to review', 'contacted' => 'Contacted', 'replied' => 'Replied'] as $value => $label)
            <a href="{{ route('admin.prospects.index', ['status' => $value]) }}" class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm hover:border-slate-400"><p class="text-xs font-semibold uppercase tracking-wide text-slate-500">{{ $label }}</p><p class="mt-2 text-2xl font-semibold">{{ $summary[$value] ?? 0 }}</p></a>
        @endforeach
    </div>
    <form method="GET" class="flex flex-wrap gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <input name="search" value="{{ request('search') }}" placeholder="Search business or email" class="min-w-64 flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="status" class="rounded-lg border border-slate-300 px-3 py-2 text-sm"><option value="">All stages</option>@foreach (\App\Models\Prospect::STATUSES as $status)<option value="{{ $status }}" @selected(request('status') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>@endforeach</select>
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm font-medium text-white">Filter</button>
    </form>
    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="divide-y divide-slate-100">
            @forelse ($prospects as $prospect)
                <a href="{{ route('admin.prospects.show', $prospect) }}" class="grid gap-3 p-4 hover:bg-slate-50 md:grid-cols-[2fr_1fr_1fr_auto] md:items-center">
                    <div><p class="font-semibold text-slate-900">{{ $prospect->business_name }}</p><p class="text-sm text-slate-500">{{ $prospect->contact_name ?: 'No contact' }} · {{ $prospect->email ?: 'No email yet' }}</p></div>
                    <div class="text-sm text-slate-600">@if ($prospect->website_url){{ parse_url($prospect->website_url, PHP_URL_HOST) }}@else<span class="font-medium text-violet-700">Website opportunity</span>@endif</div>
                    <div class="flex flex-wrap gap-2"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($prospect->status)->replace('_', ' ')->title() }}</span><span class="rounded-full px-2.5 py-1 text-xs font-semibold {{ $prospect->lead_temperature === 'warm' ? 'bg-orange-100 text-orange-800' : 'bg-sky-100 text-sky-800' }}">{{ str($prospect->lead_temperature)->title() }}</span></div>
                    <div class="text-right">@if ($prospect->opportunity_score !== null)<span class="rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold text-amber-800">{{ $prospect->opportunity_score }} opportunity</span>@else<span class="text-xs text-slate-500">{{ str($prospect->analysis_status)->title() }}</span>@endif</div>
                </a>
            @empty
                <div class="p-10 text-center"><p class="font-medium">No prospects yet</p><p class="mt-1 text-sm text-slate-500">Add a business and Sitewell will research its website automatically.</p></div>
            @endforelse
        </div>
    </div>
    {{ $prospects->links() }}
</div>
@endsection
