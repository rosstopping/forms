@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4"><div><p class="font-medium text-teal-700 text-base sm:text-sm">Prospect finder</p><h1 class="text-3xl font-semibold tracking-tight">{{ str($discovery->business_type)->headline() }} in {{ $discovery->area }}</h1><p class="mt-1 text-slate-600 text-base sm:text-sm">{{ $discovery->candidate_count }} public listings found: {{ $discovery->candidates->whereNotNull('website_url')->count() }} with websites and {{ $discovery->candidates->whereNull('website_url')->count() }} website opportunities.</p></div><a href="{{ route('admin.prospect-discoveries.index') }}" class="ui-button ui-button-secondary">New search</a></div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($discovery->status === 'pending' || $discovery->status === 'running')
        <div class="rounded-xl border border-sky-200 bg-sky-50 p-5 text-sm text-sky-900">The search is running. Refresh this page in a moment to see the results.</div>
    @elseif ($discovery->status === 'failed')
        <div class="rounded-xl border border-rose-200 bg-rose-50 p-5 text-sm text-rose-900">The search could not complete: {{ $discovery->error }}</div>
    @elseif ($discovery->candidates->isEmpty())
        <div class="ui-panel ui-section text-center"><p class="font-medium">No public business listings found</p><p class="mt-1 text-slate-500 text-base sm:text-sm">Try a larger nearby city or a different business type.</p></div>
    @else
        <form method="POST" action="{{ route('admin.prospect-discoveries.import', $discovery) }}" class="space-y-4">
            @csrf
            <div class="flex flex-wrap items-center justify-between gap-3"><p class="text-slate-600 text-base sm:text-sm">Businesses with websites are researched. Those without one become website opportunities. You still approve every email.</p><button type="submit" class="ui-button ui-button-primary">Import selected</button></div>
            <div class="ui-panel overflow-hidden">
                <div class="divide-y divide-slate-100">
                    @foreach ($discovery->candidates as $candidate)
                        <label class="flex gap-4 p-4 {{ $candidate->status !== 'new' ? 'bg-slate-50' : 'cursor-pointer hover:bg-slate-50' }}">
                            <input type="checkbox" name="candidate_ids[]" value="{{ $candidate->id }}" @disabled($candidate->status !== 'new') class="mt-1 h-4 w-4">
                            <span class="min-w-0 flex-1">
                                <span class="block font-semibold text-slate-900">{{ $candidate->business_name }}</span>
                                @if ($candidate->website_url)
                                    <a href="{{ $candidate->website_url }}" target="_blank" rel="noreferrer" class="mt-1 block truncate text-sm text-teal-700 hover:underline">{{ $candidate->website_url }}</a>
                                @else
                                    <span class="mt-1 inline-flex rounded-full bg-violet-100 px-2.5 py-1 text-xs font-semibold text-violet-800">No website listed · website opportunity</span>
                                @endif
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
