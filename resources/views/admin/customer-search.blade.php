@extends('layouts.app')
@section('content')
<div class="space-y-6">
    <header><h1 class="text-2xl font-semibold">Search performance</h1><p class="mt-2 text-sm text-slate-600">{{ $website->name }} · Recorded visibility and clicks from Google Search.</p></header>
    <section class="ui-panel ui-section @container"><h2 class="font-semibold">Performance overview</h2><x-search-progress :report="$searchProgress" /></section>
    @if($searchHistory->isNotEmpty())
        <section class="space-y-4" aria-label="Search performance graphs">
            <x-comparison-chart title="Clicks and impressions" description="Saved monthly Google Search clicks and appearances. Missing months are not recorded as zero." :points="$searchHistory" first-key="clicks" first-label="Clicks" second-key="impressions" second-label="Impressions" />
            <x-progress-chart title="Average position" description="Saved impression-weighted average position; lower is better. Monthly history is separate from the selected rolling comparison." :points="$searchHistory" value-key="position" format="decimal" :lower-is-better="true" />
        </section>
    @else
        <section class="ui-panel ui-section text-sm text-slate-500">Search performance graphs will appear when monthly history has been imported.</section>
    @endif
</div>
@endsection
