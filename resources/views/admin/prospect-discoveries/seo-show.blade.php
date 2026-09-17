@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="font-medium text-teal-700 text-base sm:text-sm">SEO Opportunities</p>
            <h1 class="text-3xl font-semibold tracking-tight">{{ $search->industry }} in {{ $search->location }}</h1>
            <p class="mt-1 text-slate-600 text-base sm:text-sm">{{ count($search->keywords) }} keywords · positions {{ $search->minimum_position }}–{{ $search->maximum_position }} · max {{ $search->maximum_pages }} pages</p>
        </div>
        <div class="flex flex-wrap gap-2"><form method="POST" action="{{ route('admin.seo-prospect-searches.rerun', $search) }}">@csrf<button type="submit" name="refresh_serps" value="0" @disabled(in_array($search->status, ['pending', 'running', 'analyzing'])) class="ui-button ui-button-secondary disabled:cursor-not-allowed">Rerun with cache</button><button type="submit" name="refresh_serps" value="1" @disabled(in_array($search->status, ['pending', 'running', 'analyzing'])) class="ui-button ui-button-secondary ml-2 disabled:cursor-not-allowed">Force fresh SERPs</button></form><a href="{{ route('admin.prospect-discoveries.index') }}#seo-opportunities" class="ui-button ui-button-secondary">Back to Find Prospects</a></div>
    </div>
    @if (session('status'))<div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>@endif
    @if ($search->error)<div class="whitespace-pre-line rounded-lg border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-800">{{ $search->error }}</div>@endif
    @error('candidate_ids')<div class="rounded-lg border border-rose-200 bg-rose-50 px-4 py-3 text-sm text-rose-800">{{ $message }}</div>@enderror

    <div class="grid gap-3 sm:grid-cols-3 xl:grid-cols-6">
        <div class="border-l-2 border-slate-950/10 pl-3"><p class="font-medium uppercase text-slate-500 text-base sm:text-sm">Status</p><p class="mt-1 font-semibold text-slate-950">{{ str($search->status)->replace('_', ' ')->title() }}</p></div>
        <div class="border-l-2 border-slate-950/10 pl-3"><p class="font-medium uppercase text-slate-500 text-base sm:text-sm">Domains</p><p class="mt-1 font-semibold text-slate-950">{{ $search->candidate_count }}</p></div>
        <div class="border-l-2 border-slate-950/10 pl-3"><p class="font-medium uppercase text-slate-500 text-base sm:text-sm">Suitable</p><p class="mt-1 font-semibold text-slate-950">{{ $search->suitable_count }}</p></div>
        <div class="border-l-2 border-slate-950/10 pl-3"><p class="font-medium uppercase text-slate-500 text-base sm:text-sm">API cost</p><p class="mt-1 font-semibold text-slate-950">${{ number_format((float) $search->api_cost, 4) }}</p></div>
        <div class="border-l-2 border-slate-950/10 pl-3"><p class="font-medium uppercase text-slate-500 text-base sm:text-sm">Estimate</p><p class="mt-1 font-semibold text-slate-950">${{ number_format((float) $search->estimated_api_cost, 4) }}</p></div>
        <div class="border-l-2 border-slate-950/10 pl-3"><p class="font-medium uppercase text-slate-500 text-base sm:text-sm">SERP source</p><p class="mt-1 font-semibold text-slate-950">{{ $search->fresh_keyword_count }} fresh · {{ $search->cached_keyword_count }} cached</p></div>
    </div>

    @if (filled($search->serp_freshness))
        <details class="ui-well px-4 py-3">
            <summary class="cursor-pointer text-sm font-semibold text-slate-800">SERP freshness by keyword</summary>
            <dl class="mt-3 grid gap-2 text-sm sm:grid-cols-2">
                @foreach ($search->serp_freshness as $keyword => $freshness)
                    <div class="flex items-center justify-between gap-3 border-b border-slate-950/10 pb-2">
                        <dt class="text-slate-700">{{ $keyword }}</dt>
                        <dd class="shrink-0 text-xs font-medium text-slate-500">{{ str($freshness['source'] ?? 'unknown')->title() }} · {{ isset($freshness['fetched_at']) ? \Illuminate\Support\Carbon::parse($freshness['fetched_at'])->diffForHumans() : 'Unknown' }}</dd>
                    </div>
                @endforeach
            </dl>
            <p class="mt-3 text-slate-500 text-base sm:text-sm">Cached SERPs are reused for {{ config('services.dataforseo.serp_cache_days') }} days unless a fresh rerun is requested.</p>
        </details>
    @endif

    <form method="GET" action="{{ route('admin.seo-prospect-searches.show', $search) }}" class="ui-well grid gap-3 p-4 sm:grid-cols-4 sm:items-end">
        <label class="ui-label grid gap-1">
            Qualification
            <select name="qualification" class="ui-input">
                <option value="">All statuses</option>
                @foreach (['suitable', 'too_large', 'unsuitable', 'analysis_failed', 'pending_analysis', 'analyzing'] as $status)
                    <option value="{{ $status }}" @selected(($filters['qualification'] ?? '') === $status)>{{ str($status)->replace('_', ' ')->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="ui-label grid gap-1">
            Migration
            <select name="migration" class="ui-input">
                <option value="">All difficulties</option>
                @foreach (['easy', 'medium', 'hard', 'unknown'] as $difficulty)
                    <option value="{{ $difficulty }}" @selected(($filters['migration'] ?? '') === $difficulty)>{{ str($difficulty)->title() }}</option>
                @endforeach
            </select>
        </label>
        <label class="ui-label grid gap-1">
            Minimum score
            <input type="number" name="minimum_score" min="0" max="100" value="{{ $filters['minimum_score'] ?? '' }}" placeholder="Any score" class="ui-input">
        </label>
        <div class="flex gap-2">
            <button type="submit" class="ui-button ui-button-primary">Apply filters</button>
            <a href="{{ route('admin.seo-prospect-searches.show', $search) }}" class="ui-button ui-button-secondary">Clear</a>
        </div>
    </form>

    <form method="POST" action="{{ route('admin.seo-prospect-searches.import', $search) }}" class="space-y-3" data-outreach-selection-form>
        @csrf
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex flex-wrap items-center gap-4">
                <label class="ui-label inline-flex cursor-pointer items-center gap-2"><input type="checkbox" data-outreach-select-all class="size-4 disabled:cursor-not-allowed disabled:opacity-40">Select all eligible</label>
                <p class="text-slate-500 text-base sm:text-sm">Selects suitable domains that are not already in Outreach.</p>
            </div>
            <button type="submit" class="ui-button ui-button-primary">Add selected to Outreach</button>
        </div>
    <div class="overflow-x-auto border-y border-slate-950/10 bg-white">
        <table class="min-w-full divide-y divide-slate-950/10 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3"><span class="sr-only">Select</span></th><th class="px-4 py-3">Business / domain</th><th class="px-4 py-3">Score</th><th class="px-4 py-3">Pages</th><th class="px-4 py-3">Best ranking</th><th class="px-4 py-3">Audit issues</th><th class="px-4 py-3">Migration</th><th class="px-4 py-3">Observations</th><th class="px-4 py-3">Qualification</th><th class="px-4 py-3">Outreach</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($search->candidates as $candidate)
                    @php($bestRanking = $candidate->rankings->sortBy('position')->first())
                    <tr>
                        <td class="px-4 py-4"><input type="checkbox" name="candidate_ids[]" value="{{ $candidate->id }}" data-outreach-candidate @disabled($candidate->qualification_status !== 'suitable' || $candidate->prospect) aria-label="Select {{ $candidate->business_name ?: $candidate->domain }}" class="size-4 disabled:cursor-not-allowed disabled:opacity-40"></td>
                        <td class="px-4 py-4"><a href="{{ $candidate->website_url }}" target="_blank" rel="noreferrer" class="font-semibold text-teal-700 hover:underline">{{ $candidate->business_name ?: $candidate->domain }}</a><p class="mt-1 text-slate-500 text-base sm:text-sm">{{ $candidate->domain }}</p></td>
                        <td class="px-4 py-4">@if ($candidate->opportunity_score !== null)<span class="text-lg font-semibold tabular-nums text-slate-950">{{ $candidate->opportunity_score }}</span><span class="text-xs text-slate-500">/100</span>@if (filled($candidate->score_breakdown))<details class="mt-1"><summary class="cursor-pointer text-xs font-medium text-teal-700">Breakdown</summary><ul class="mt-2 grid gap-1 text-xs text-slate-600">@foreach ($candidate->score_breakdown as $component)<li>{{ $component['score'] }}/{{ $component['maximum'] }} · {{ $component['explanation'] }}</li>@endforeach</ul></details>@endif @else<span class="text-slate-500">—</span>@endif</td>
                        <td class="px-4 py-4">@if ($candidate->page_count !== null)<span class="font-semibold tabular-nums">{{ $candidate->page_count }}</span><p class="mt-1 text-slate-500 text-base sm:text-sm">{{ str(data_get($candidate->observations, 'page_count_band', 'unknown'))->headline() }}</p>@else<span class="text-slate-500">—</span>@endif</td>
                        <td class="px-4 py-4">@if ($bestRanking)<span class="font-semibold">#{{ $bestRanking->position }}</span><p class="mt-1 max-w-xs text-slate-500 text-base sm:text-sm">{{ $bestRanking->keyword }}</p>@else — @endif</td>
                        <td class="px-4 py-4">@if ($candidate->audit_score !== null)<span class="font-semibold tabular-nums">{{ $candidate->audit_score }}/100</span><p class="mt-1 text-slate-500 text-base sm:text-sm">{{ collect($candidate->audit_findings)->whereIn('severity', ['warning', 'failed'])->count() }} findings · {{ count(data_get($candidate->contact_details, 'emails', [])) }} emails</p>@elseif ($candidate->qualification_status === 'too_large')<span class="text-slate-500">Skipped</span>@else<span class="text-slate-500">—</span>@endif</td>
                        <td class="max-w-xs px-4 py-4"><span class="font-semibold">{{ str($candidate->migration_difficulty)->title() }}</span><p class="mt-1 leading-5 text-slate-500 text-base sm:text-sm">{{ $candidate->migration_difficulty_reason }}</p></td>
                        <td class="max-w-sm px-4 py-4">@forelse (collect(data_get($candidate->observations, 'outreach', []))->take(2) as $observation)<p class="leading-5 text-slate-600 text-base sm:text-sm">{{ $observation['summary'] }}</p>@empty<span class="text-slate-500">—</span>@endforelse</td>
                        <td class="max-w-xs px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($candidate->qualification_status)->replace('_', ' ')->title() }}</span>@if ($candidate->analysis_error)<p class="mt-2 leading-5 text-rose-700 text-base sm:text-sm">{{ $candidate->analysis_error }}</p>@endif</td>
                        <td class="px-4 py-4">@if ($candidate->prospect)<a href="{{ route('admin.prospects.show', $candidate->prospect) }}" class="font-semibold text-teal-700 hover:underline">Already in Outreach</a>@else<span class="text-slate-500">Not imported</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-12 text-center text-slate-500">No candidates match these filters.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    </form>
    <p class="text-slate-500 text-base sm:text-sm">Scores and observations are generated only from stored ranking, crawl, and audit evidence. Import prepares a draft but never sends email; approval remains mandatory.</p>
</div>
@endsection
