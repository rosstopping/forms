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
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500"><tr><th class="px-4 py-3">Business / domain</th><th class="px-4 py-3">Pages</th><th class="px-4 py-3">Best ranking</th><th class="px-4 py-3">Audit issues</th><th class="px-4 py-3">Migration</th><th class="px-4 py-3">Qualification</th><th class="px-4 py-3">Outreach</th></tr></thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($search->candidates as $candidate)
                    @php($bestRanking = $candidate->rankings->sortBy('position')->first())
                    <tr>
                        <td class="px-4 py-4"><a href="{{ $candidate->website_url }}" target="_blank" rel="noreferrer" class="font-semibold text-teal-700 hover:underline">{{ $candidate->business_name ?: $candidate->domain }}</a><p class="mt-1 text-xs text-slate-500">{{ $candidate->domain }}</p></td>
                        <td class="px-4 py-4">@if ($candidate->page_count !== null)<span class="font-semibold tabular-nums">{{ $candidate->page_count }}</span><p class="mt-1 text-xs text-slate-500">{{ str(data_get($candidate->observations, 'page_count_band', 'unknown'))->headline() }}</p>@else<span class="text-slate-500">—</span>@endif</td>
                        <td class="px-4 py-4">@if ($bestRanking)<span class="font-semibold">#{{ $bestRanking->position }}</span><p class="mt-1 max-w-xs text-xs text-slate-500">{{ $bestRanking->keyword }}</p>@else — @endif</td>
                        <td class="px-4 py-4">@if ($candidate->audit_score !== null)<span class="font-semibold tabular-nums">{{ $candidate->audit_score }}/100</span><p class="mt-1 text-xs text-slate-500">{{ collect($candidate->audit_findings)->whereIn('severity', ['warning', 'failed'])->count() }} findings · {{ count(data_get($candidate->contact_details, 'emails', [])) }} emails</p>@elseif ($candidate->qualification_status === 'too_large')<span class="text-slate-500">Skipped</span>@else<span class="text-slate-500">—</span>@endif</td>
                        <td class="max-w-xs px-4 py-4"><span class="font-semibold">{{ str($candidate->migration_difficulty)->title() }}</span><p class="mt-1 text-xs leading-5 text-slate-500">{{ $candidate->migration_difficulty_reason }}</p></td>
                        <td class="max-w-xs px-4 py-4"><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($candidate->qualification_status)->replace('_', ' ')->title() }}</span>@if ($candidate->analysis_error)<p class="mt-2 text-xs leading-5 text-rose-700">{{ $candidate->analysis_error }}</p>@endif</td>
                        <td class="px-4 py-4">@if ($candidate->prospect)<a href="{{ route('admin.prospects.show', $candidate->prospect) }}" class="font-semibold text-teal-700 hover:underline">Already in Outreach</a>@else<span class="text-slate-500">Not imported</span>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-12 text-center text-slate-500">No ranking domains have been stored yet.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <p class="text-xs text-slate-500">Candidates are crawled and audited independently. Opportunity scoring and Add to Outreach remain disabled until the next phases are complete.</p>
</div>
@endsection