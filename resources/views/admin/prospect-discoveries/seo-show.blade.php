@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-teal-700">SEO Opportunities</p>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $search->industry }} in {{ $search->location }}</h1>
            <p class="mt-1 text-sm text-slate-600">{{ count($search->keywords) }} keywords · positions {{ $search->minimum_position }}–{{ $search->maximum_position }} · max {{ $search->maximum_pages }} pages</p>
        </div>
        <a href="{{ route('admin.prospect-discoveries.index') }}#seo-opportunities" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Back to Find Prospects</a>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($search->error)<div class="whitespace-pre-line rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $search->error }}</div>@endif

    <div class="grid gap-3 sm:grid-cols-4">
        <div class="border-l-2 border-slate-300 pl-3"><p class="text-xs font-medium uppercase text-slate-500">Status</p><p class="mt-1 font-semibold text-slate-950">{{ str($search->status)->replace('_', ' ')->title() }}</p></div>
        <div class="border-l-2 border-slate-300 pl-3"><p class="text-xs font-medium uppercase text-slate-500">Domains</p><p class="mt-1 font-semibold text-slate-950">{{ $search->candidate_count }}</p></div>
        <div class="border-l-2 border-slate-300 pl-3"><p class="text-xs font-medium uppercase text-slate-500">Suitable</p><p class="mt-1 font-semibold text-slate-950">{{ $search->suitable_count }}</p></div>
        <div class="border-l-2 border-slate-300 pl-3"><p class="text-xs font-medium uppercase text-slate-500">API cost</p><p class="mt-1 font-semibold text-slate-950">${{ number_format((float) $search->api_cost, 4) }}</p></div>
    </div>

    <form method="GET" action="{{ route('admin.seo-prospect-searches.show', $search) }}" class="grid gap-3 rounded-lg border border-slate-200 bg-slate-50 p-4 sm:grid-cols-4 sm:items-end">
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            Qualification
            <select name="qualification" class="rounded-lg border-slate-300 bg-white text-sm">
                <option value="">All statuses</option>
                @foreach (['suitable', 'too_large', 'unsuitable', 'analysis_failed', 'pending_analysis', 'analyzing'] as $status)
                    <option value="{{ $status }}" @selected(($filters['qualification'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            Migration
            <select name="migration" class="rounded-lg border-slate-300 bg-white text-sm">
                <option value="">All difficulties</option>
                @foreach (['easy', 'medium', 'hard', 'unknown'] as $difficulty)
                    <option value="{{ $difficulty }}" @selected(($filters['migration'] ?? '') === $difficulty)>{{ str($difficulty)->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="grid gap-1 text-sm font-medium text-slate-700">
            Minimum score
            <input type="number" name="minimum_score" min="0" max="100" value="{{ $filters['minimum_score'] ?? '' }}" placeholder="Any score" class="rounded-lg border-slate-300 bg-white text-sm">
        </label>
        <div class="flex gap-2">
            <button type="submit" class="rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-700">Apply filters</button>
            <a href="{{ route('admin.seo-prospect-searches.show', $search) }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-700 hover:bg-slate-100">Clear</a>
        </div>
    </form>

    <div class="overflow-x-auto border-y border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">Business / domain</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">Pages</th><th class="px-4 py-3">Best ranking</th><th class="px-4 py-3">Audit issues</th><th class="px-4 py-3">Migration</th><th class="px-4 py-3">Observations</th><th class="px-4 py-3">Qualification</th><th class="px-4 py-3">Outreach</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($search->candidates as $candidate)
                    @php($bestRanking = $candidate->rankings->sortBy('position')->first())
                    <tr>
                        <td class="px-4 py-4"><a href="{{ $candidate->website_url }}" target="_blank" rel="noreferrer" class="font-semibold text-teal-700 hover:underline">{{ $candidate->business_name ?: $candidate->domain }}</a><p class="mt-1 text-xs text-slate-500">{{ $candidate->domain }}</p></td>
                        <td class="px-4 py-4">@if ($candidate->opportunity_score !== null)<span class="text-lg font-semibold tabular-nums text-slate-950">{{ $candidate->opportunity_score }}</span><span class="text-xs text-slate-500">/100</span>@if (filled($candidate->score_breakdown))<details class="mt-1"><summary class="cursor-pointer text-xs font-medium text-teal-700">Breakdown</summary><ul class="mt-2 grid gap-1 text-xs text-slate-600">@foreach ($candidate->score_breakdown as $component)<li>{{ $component['score'] }}/{{ $component['maximum'] }} · {{ $component['explanation'] }}</li>@endforeach</ul></details>@endif @else<span class="text-slate-500">—</span>@endif</td>
                        <td class="px-4 py-4">@if ($candidate->page_count !== null)<span class="font-semibold tabular-nums">{{ $candidate->page_count }}</span><p class="mt-1 text-xs text-slate-500">{{ str(data_get($candidate->observations, 'page_count_band', 'unknown'))->headline() }}</p>@else<span class="text-slate-500">—</span>@endif</td>
                        <td class="px-4 py-4">@if ($bestRanking)<span class="font-semibold">#{{ $bestRanking->position }}</span><p class="mt-1 max-w-xs text-xs text-slate-500">{{ $bestRanking->keyword }}</p>@else — @endif</td>
                        <td class="px-4 py-4">@if ($candidate->audit_score !== null)<span class="font-semibold tabular-nums">{{ $candidate->audit_score }}/100</span><p class="mt-1 text-xs text-slate-500">{{ collect($candidate->audit_findings)->whereIn('severity', ['warning', 'failed'])->count() }} findings · {{ count(data_get($candidate->contact_details, 'emails', [])) }} emails</p>@elseif ($candidate->qualification_status === 'too_large')<span class="text-slate-500">Skipped</span>@else<span class="text-slate-500">—</span>@endif</td>
                        <td class="max-w-xs px-4 py-4"><span class="font-semibold">{{ str($candidate->migration_difficulty)->title() }}</span><p class="mt-1 text-xs leading-5 text-slate-500">{{ $candidate->migration_difficulty_reason }}</p></td>
                        <td class="max-w-sm px-4 py-4">@forelse (collect(data_get($candidate->observations, 'outreach', []))->take(2) as $observation)<p class="text-xs leading-5 text-slate-600">{{ $observation['summary'] }}</p>@empty<span class="text-slate-500">—</span>@endforelse</td>
                        <td class="max-w-xs px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($candidate->qualification_status)->replace('_', ' ')->title() }}</span>@if ($candidate->analysis_error)<p class="mt-2 text-xs leading-5 text-rose-700">{{ $candidate->analysis_error }}</p>@endif</td>
                        <td class="px-4 py-4">@if ($candidate->prospect)<a href="{{ route('admin.prospects.show', $candidate->prospect) }}" class="font-semibold text-teal-700 hover:underline">Already in Outreach</a>@else<span class="text-slate-500">Not imported</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="px-4 py-12 text-center text-slate-500">No candidates match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-500">Scores and observations are generated only from stored ranking, crawl, and audit evidence. Add to Outreach remains disabled until the next phase is complete.</p>
</div>
@endsection
