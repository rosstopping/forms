@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="text-sm font-medium text-teal-700">Prospect finder</p><h1 class="text-3xl font-semibold tracking-tight">{{ str($discovery->business_type)->headline() }} in {{ $discovery->area }}</h1><p class="mt-1 text-sm text-slate-600">{{ $discovery->candidate_count }} public listings found. Select the businesses you want to research.</p></div><a href="{{ route('admin.prospect-discoveries.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">New search</a></div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($discovery->status === 'pending' || $discovery->status === 'running')
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">The search is running. Refresh this page in a moment to see the results.</div>
    @elseif ($discovery->status === 'failed')
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-900">The search could not complete: {{ $discovery->error }}</div>
    @elseif ($discovery->candidates->isEmpty())
        <div class="rounded-xl border border-slate-200 bg-white p-8 text-center"><p class="font-medium">No public websites found</p><p class="mt-1 text-sm text-slate-500">Try a larger nearby city or a different business type.</p></div>
    @else
        <form method="POST" action="{{ route('admin.prospect-discoveries.import', $discovery) }}" class="space-y-4">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-sm text-slate-600">Importing queues a health review only. You still review and approve any email in Outreach.</p><button class="rounded-lg bg-slate-950 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800">Import selected for research</button></div>
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <div class="divide-y divide-slate-100">
                    @foreach ($discovery->candidates as $candidate)
                        <label class="flex gap-4 p-4 {{ $candidate->status !== 'new' ? 'bg-slate-50' : 'cursor-pointer hover:bg-slate-50' }}">
                            <input type="checkbox" name="candidate_ids[]" value="{{ $candidate->id }}" @disabled($candidate->status !== 'new') class="mt-1 h-4 w-4 rounded border-slate-300 text-slate-900">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-slate-900">{{ $candidate->business_name }}</span>
                                <a href="{{ $candidate->website_url }}" target="_blank" rel="noreferrer" class="mt-1 block truncate text-sm text-teal-700 hover:underline">{{ $candidate->website_url }}</a>
                                @if ($candidate->address)
                                    <span class="mt-1 block text-sm text-slate-500">{{ $candidate->address }}</span>
                                @endif
                            </span>
                            <span class="text-right text-xs text-slate-500">
                                @if ($candidate->status === 'imported')
                                    Imported
                                @endif
                            </span>
                        </label>
                    @endforeach
                </div>
            </div>
        </form>
    @endif
</div>
@endsection
