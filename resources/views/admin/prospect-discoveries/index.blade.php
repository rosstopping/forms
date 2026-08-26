@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <div class="flex flex-wrap items-end justify-between gap-4">
        <div>
            <p class="text-sm font-medium text-teal-700">Lead generation</p>
            <h1 class="text-3xl font-semibold tracking-tight">Find prospects</h1>
            <p class="mt-1 text-sm text-slate-600">Find businesses, review the evidence, then choose which prospects enter Outreach.</p>
        </div>
        <div class="flex flex-wrap gap-2"><a href="{{ route('admin.prospecting-industry-profiles.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Automation strategy</a><a href="{{ route('admin.prospects.index') }}" class="rounded-lg border border-slate-300 bg-white px-4 py-2.5 text-sm font-semibold text-slate-800 hover:bg-slate-50">Back to Outreach</a></div>
    </div>

    @if (session('status'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('status') }}</div>
    @endif

    <div class="space-y-6" data-tabs data-tabs-key="prospect-discovery" data-tabs-hash data-default-tab="{{ old('industry') ? 'seo-opportunities' : 'automatic-prospecting' }}">
    <div class="flex w-fit gap-1 rounded-lg border border-slate-200 bg-slate-100 p-1" role="tablist" aria-label="Discovery modes">
        <button type="button" id="prospect-discovery-tab-automatic" class="rounded-md px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-white hover:text-slate-900 aria-selected:bg-white aria-selected:text-slate-900 aria-selected:shadow-sm" role="tab" aria-selected="true" aria-controls="automatic-prospecting" tabindex="0" data-tab="automatic-prospecting">Automatic</button>
        <button type="button" id="prospect-discovery-tab-seo" class="rounded-md px-3 py-2 text-sm font-semibold text-slate-600 hover:bg-white hover:text-slate-900 aria-selected:bg-white aria-selected:text-slate-900 aria-selected:shadow-sm" role="tab" aria-selected="false" aria-controls="seo-opportunities" tabindex="-1" data-tab="seo-opportunities">SEO Opportunities</button>
    </div>

    <section id="automatic-prospecting" class="space-y-4 scroll-mt-6" role="tabpanel" aria-labelledby="prospect-discovery-tab-automatic" data-tab-panel="automatic-prospecting">
        <div><h2 class="text-xl font-semibold text-slate-950">Automatic prospecting</h2><p class="mt-1 text-sm text-slate-600">Work through enabled high-value industries and locations in priority order using the existing SEO discovery pipeline.</p></div>
        <div class="rounded-lg border border-teal-200 bg-teal-50/60 p-5 shadow-sm">
            <form method="POST" action="{{ route('admin.prospect-discoveries.automatic') }}" class="grid gap-4 md:grid-cols-[minmax(0,1fr)_12rem_auto] md:items-end">
                @csrf
                <div><p class="text-sm font-semibold text-slate-950">Find the next commercial opportunities</p><p class="mt-1 text-sm leading-6 text-slate-600">Uses up to three strong local searches per industry/location pair. Combinations already run this month are skipped, recent domain analysis is reused, and qualifying prospects still require outreach approval.</p></div>
                <label class="grid gap-1.5 text-sm font-medium text-slate-700">Keyword operation limit<input type="number" name="limit" value="{{ old('limit', 50) }}" min="1" max="200" required class="rounded-lg border border-slate-300 bg-white px-3 py-2.5 text-sm"><span class="text-xs font-normal text-slate-500">Maximum paid/cached SERP lookups.</span></label>
                <button class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800">Run automatic prospecting</button>
            </form>
            @error('limit')<p class="mt-3 text-sm text-rose-700">{{ $message }}</p>@enderror
        </div>
    </section>

    <section id="seo-opportunities" class="space-y-4 scroll-mt-6" role="tabpanel" aria-labelledby="prospect-discovery-tab-seo" data-tab-panel="seo-opportunities" hidden>
        <div><h2 class="text-xl font-semibold text-slate-950">SEO Opportunities</h2><p class="mt-1 text-sm text-slate-600">Find websites already appearing in Google with room to improve for useful local searches.</p></div>
        <div class="rounded-lg border border-slate-200 bg-white p-5 shadow-sm">
            <form method="POST" action="{{ route('admin.seo-prospect-searches.store') }}" class="grid gap-4" data-seo-cost-estimate-form data-cost-per-ten="{{ $serpLiveCostPerTen }}">
                @csrf
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Industry / service<input name="industry" value="{{ old('industry') }}" required placeholder="e.g. Roofing" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Location<input name="location" value="{{ old('location') }}" required placeholder="e.g. Barnsley" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Service keywords<textarea name="service_keywords" rows="5" required placeholder="roofer&#10;roof repairs&#10;flat roofing" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm">{{ is_array(old('service_keywords')) ? implode("\n", old('service_keywords')) : old('service_keywords') }}</textarea></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Final search keywords<textarea name="keywords" rows="5" placeholder="roofer barnsley&#10;roof repairs barnsley&#10;flat roofing barnsley" class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm" data-seo-cost-keywords>{{ is_array(old('keywords')) ? implode("\n", old('keywords')) : old('keywords') }}</textarea></label>
                </div>
                <div class="grid gap-4 sm:grid-cols-3">
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Minimum position<input type="number" name="minimum_position" value="{{ old('minimum_position', 20) }}" min="1" max="100" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Maximum position<input type="number" name="maximum_position" value="{{ old('maximum_position', 100) }}" min="10" max="100" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm" data-seo-cost-depth></label>
                    <label class="grid gap-1.5 text-sm font-medium text-slate-700">Maximum site size<input type="number" name="maximum_pages" value="{{ old('maximum_pages', 20) }}" min="1" max="100" required class="rounded-lg border border-slate-300 px-3 py-2.5 text-sm"></label>
                </div>
                @if (! $dataForSeoConfigured)<p class="rounded-md border border-amber-200 bg-amber-50 px-3 py-2 text-sm text-amber-800">DataForSEO credentials are required before SEO opportunity searches can run.</p>@endif
                @if ($errors->any())<div class="text-sm text-rose-700">{{ $errors->first() }}</div>@endif
                <div class="rounded-lg bg-slate-50 px-3 py-2 text-sm text-slate-700"><span class="font-semibold">Estimated provider cost:</span> <output data-seo-cost-output>$0.0000</output><span class="text-xs text-slate-500"> · identical SERPs up to {{ $serpCacheDays }} days old are reused at no provider cost</span></div>
                <div><button @disabled(! $dataForSeoConfigured) class="rounded-lg bg-teal-700 px-4 py-2.5 text-sm font-semibold text-white hover:bg-teal-800 disabled:cursor-not-allowed disabled:opacity-50">Find ranking opportunities</button></div>
            </form>
            <p class="mt-4 text-xs leading-5 text-slate-500">Search results are saved for review. Suitable candidates are added to Outreach only when you select them; outreach still requires manual approval.</p>
        </div>
        <div class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm"><div class="divide-y divide-slate-100">
            @forelse ($seoSearches as $search)
                <a href="{{ route('admin.seo-prospect-searches.show', $search) }}" class="grid gap-2 p-4 hover:bg-slate-50 md:grid-cols-[1fr_auto_auto] md:items-center"><div><p class="font-semibold text-slate-900">{{ $search->industry }} in {{ $search->location }}</p><p class="mt-1 text-sm text-slate-500">{{ count($search->keywords) }} keywords · {{ $search->fresh_keyword_count }} fresh / {{ $search->cached_keyword_count }} cached · ${{ number_format((float) $search->api_cost, 4) }} actual · {{ $search->created_at->format('j M Y') }}</p></div><span class="text-sm text-slate-600">{{ $search->candidates_count }} domains</span><span class="rounded-full bg-slate-100 px-2.5 py-1 text-xs font-medium text-slate-700">{{ str($search->status)->replace('_', ' ')->title() }}</span></a>
            @empty
                <div class="p-10 text-center"><p class="font-medium">No SEO opportunity searches yet</p><p class="mt-1 text-sm text-slate-500">Set the services and location to discover ranking domains.</p></div>
            @endforelse
        </div></div>
        {{ $seoSearches->links() }}
    </section>
    </div>
</div>
@endsection
