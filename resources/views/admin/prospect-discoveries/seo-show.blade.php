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

    <div class="overflow-x-auto border-y border-slate-200 bg-white">
        <table class="min-w-full divide-y divide-slate-200 text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">Business / domain</th><th class="px-4 py-3">Best ranking</th><th class="px-4 py-3">Keywords</th><th class="px-4 py-3">Analysis</th><th class="px-4 py-3">Outreach</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($search->candidates as $candidate)
                    @php($bestRanking = $candidate->rankings->sortBy('position')->first())
                    <tr>
                        <td class="px-4 py-4"><a href="{{ $candidate->website_url }}" target="_blank" rel="noreferrer" class="font-semibold text-teal-700 hover:underline">{{ $candidate->business_name ?: $candidate->domain }}</a><p class="mt-1 text-xs text-slate-500">{{ $candidate->domain }}</p></td>
                        <td class="px-4 py-4">@if ($bestRanking)<span class="font-semibold">#{{ $bestRanking->position }}</span><p class="mt-1 max-w-xs text-xs text-slate-500">{{ $bestRanking->keyword }}</p>@else — @endif</td>
                        <td class="px-4 py-4">{{ $candidate->rankings->count() }}</td>
                        <td class="px-4 py-4 text-slate-600">Pending crawl and audit</td>
                        <td class="px-4 py-4">@if ($candidate->prospect)<a href="{{ route('admin.prospects.show', $candidate->prospect) }}" class="font-semibold text-teal-700 hover:underline">Already in Outreach</a>@else<span class="text-slate-500">Not imported</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-12 text-center text-slate-500">No ranking domains have been stored yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-500">Phase 1 stores provider-backed ranking evidence only. Crawl, audit, scoring, qualification and Add to Outreach remain disabled until the analysis pipeline is complete.</p>
</div>
@endsection