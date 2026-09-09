@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header class="space-y-3">
        <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'competitors']) }}" class="text-sm font-medium text-slate-600 hover:underline">← Back to competitors</a>
        <h1 class="break-all text-2xl font-semibold tracking-tight text-slate-950">{{ $audit->competitor_domain }}</h1>
        <p class="text-sm text-slate-600">Compared with {{ $audit->domain }} · {{ strtoupper($audit->language_code) }} · Location {{ $audit->location_code }} · {{ ucfirst(str_replace('_', ' ', $audit->status)) }}</p>
        <p class="text-sm text-slate-500">Third-party estimates, not Search Console measurements. Collected {{ $audit->started_at?->format('j M Y, H:i') ?? 'Pending' }}. Provider cost: ${{ number_format((float) $cost, 4) }}.</p>
        <p class="text-sm text-slate-500">Sample limits: {{ $audit->limits['ranked_keywords'] }} ranked, {{ $audit->limits['shared_keywords'] }} shared and {{ $audit->limits['missing_keywords'] }} missing keywords; {{ $audit->limits['leading_pages'] }} leading pages and {{ $audit->limits['analyse_pages'] }} page analyses. Missing means not observed by the provider in this market.</p>
        <div class="flex flex-wrap items-center gap-3">
            @if ($canManageWebsite && ! $audit->competitor->excluded)<form method="POST" action="{{ route('admin.competitors.audit', [$website, $audit->competitor]) }}">@csrf<button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white">{{ $audit->status === 'failed' ? 'Retry audit' : 'Refresh audit' }}</button></form>@endif
            <a href="{{ route('admin.competitor-audits.show', [$website, $audit]) }}" class="text-sm font-medium text-teal-700 hover:underline">Check progress</a>
        </div>
        @if (in_array($audit->status, ['pending', 'processing']))<p role="status" class="rounded-md bg-teal-50 p-3 text-sm text-teal-900">Audit in progress. {{ count($audit->stages ?? []) }} of 7 stages saved. Check progress to see new results.</p>@endif
        @foreach ($audit->errors ?? [] as $stage => $error)<p role="alert" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">{{ ucfirst(str_replace('_', ' ', $stage)) }}: {{ $error }}</p>@endforeach
    </header>
    <section class="space-y-4" aria-labelledby="competitor-opportunities-heading">
        <h2 id="competitor-opportunities-heading" class="text-lg font-semibold text-slate-950">Content opportunities</h2>
        <p class="text-sm text-slate-600">Observed page features inform these briefs; hypotheses about ranking success are not proven causes. Use original writing and verified facts.</p>
        @forelse ($audit->opportunities as $opportunity)
            <article class="space-y-4 rounded-xl border bg-white p-4 shadow-sm sm:p-6">
                <h3 class="font-semibold text-slate-950">{{ $opportunity->title }}</h3>
                <p class="text-sm text-slate-600">{{ $opportunity->brief['relevance_reason'] ?? '' }}</p>
                <p class="text-sm text-slate-600">{{ $opportunity->brief['content_format'] ?? '' }} · {{ $opportunity->brief['search_intent'] ?? '' }}</p>
                <p class="break-words text-sm text-slate-600">Target: {{ $opportunity->brief['existing_page_url'] ?: 'Choose after checking existing coverage' }}</p>
                <div class="grid gap-4 md:grid-cols-2">
                    @foreach (['observations' => 'Observed strengths', 'ranking_hypotheses' => 'Ranking hypotheses', 'gaps' => 'Content gaps', 'improvements' => 'Original improvements', 'outline' => 'Suggested outline'] as $key => $label)
                        <div><h4 class="text-sm font-medium text-slate-900">{{ $label }}</h4><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">@foreach ($opportunity->brief[$key] ?? [] as $item)<li>{{ $item }}</li>@endforeach</ul></div>
                    @endforeach
                </div>
                <div class="space-y-1 text-sm"><p class="font-medium">Sources</p>@foreach ($opportunity->brief['source_urls'] ?? [] as $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="block break-all text-teal-700 hover:underline">{{ $url }}</a>@endforeach</div>
                @if ($opportunity->content_request_id)<p class="text-sm font-medium text-teal-700">Queued in Content</p>
                @elseif ($canManageWebsite && ! $audit->competitor->excluded)<form method="POST" action="{{ route('admin.competitor-opportunities.queue', [$website, $opportunity]) }}">@csrf<button class="rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white">Queue content brief</button></form>@endif
            </article>
        @empty
            <p class="rounded-xl border bg-white p-6 text-sm text-slate-500">No content briefs yet. Briefs need relevant keywords and successfully analysed pages; limited evidence may produce no recommendations.</p>
        @endforelse
    </section>
    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="competitor-keywords-heading">
        <h2 id="competitor-keywords-heading" class="text-lg font-semibold">Keywords</h2>
        <nav class="my-4 flex flex-wrap gap-3 text-sm" aria-label="Keyword filters">@foreach (['all' => 'All keywords', 'missing' => 'Missing keywords', 'outranked' => 'They outrank us'] as $value => $label)<a href="{{ route('admin.competitor-audits.show', [$website, $audit, 'filter' => $value]) }}" @if ($filter === $value) aria-current="page" @endif class="rounded-md border px-3 py-2 aria-[current=page]:bg-slate-100">{{ $label }}</a>@endforeach</nav>
        <div class="overflow-x-auto"><table class="w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">Keyword / pages</th><th class="p-3">Competitor</th><th class="p-3">Our position</th><th class="p-3">Volume</th><th class="p-3">Intent</th><th class="p-3">Difficulty</th></tr></thead><tbody>
            @forelse ($keywords as $keyword)<tr class="border-b border-slate-100"><td class="min-w-64 p-3"><span class="font-medium">{{ $keyword->keyword }}</span><span class="mt-1 block break-all text-xs text-slate-500">{{ $keyword->ranking_url }}</span>@if ($keyword->our_ranking_url)<span class="mt-1 block break-all text-xs text-slate-500">Ours: {{ $keyword->our_ranking_url }}</span>@endif</td><td class="p-3">{{ $keyword->position }}</td><td class="p-3">{{ $keyword->our_position ?? ($keyword->comparison === 'missing' ? 'Not observed' : 'Unknown') }}</td><td class="p-3">{{ $keyword->search_volume === null ? '—' : number_format($keyword->search_volume) }}</td><td class="p-3">{{ $keyword->search_intent ?? '—' }}</td><td class="p-3">{{ $keyword->keyword_difficulty ?? '—' }}</td></tr>
            @empty<tr><td colspan="6" class="p-6 text-center text-slate-500">No keywords in this sample.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $keywords->links() }}</div>
    </section>
    <section class="space-y-4" aria-labelledby="competitor-pages-heading">
        <h2 id="competitor-pages-heading" class="text-lg font-semibold">Leading pages</h2>
        @forelse ($audit->pages as $page)
            <details class="rounded-xl border bg-white p-4 shadow-sm sm:p-6"><summary class="cursor-pointer break-all font-medium text-slate-900">{{ $page->url }} <span class="text-sm font-normal text-slate-500">· {{ $page->status === 'pending' ? 'Outside analysed sample' : ucfirst($page->status) }} · {{ $page->estimated_traffic === null ? 'Traffic unknown' : '~'.number_format((float) $page->estimated_traffic).' estimated visits' }}</span></summary>
                <div class="mt-4 space-y-3 text-sm text-slate-600">
                    <p>Associated sampled keywords: {{ ($pageKeywords->get($page->url) ?? collect())->pluck('keyword')->take(12)->implode(', ') ?: 'None in keyword sample' }}</p>
                    <p>Fetched: {{ $page->fetched_at?->format('j M Y, H:i') ?? 'Not fetched' }}</p>
                    @if ($page->error)<p class="text-amber-800">{{ $page->error }}</p>@endif
                    @if ($page->analysis)
                        <p class="font-medium">{{ $page->analysis['title'] ?? '' }}</p><p>{{ $page->analysis['meta_description'] ?? '' }}</p>
                        <p>Structured data: {{ implode(', ', $page->analysis['structured_data_types'] ?? []) ?: 'None observed' }}</p>
                        <ul class="space-y-1">@foreach ($page->analysis['headings'] ?? [] as $heading)<li>{{ strtoupper($heading['level']) }}: {{ $heading['text'] }}</li>@endforeach</ul>
                        <details><summary class="cursor-pointer font-medium">Extracted main content</summary><p class="mt-2 whitespace-pre-line">{{ $page->analysis['main_content'] ?? '' }}</p></details>
                    @endif
                </div>
            </details>
        @empty<p class="text-sm text-slate-500">No leading pages returned yet.</p>@endforelse
    </section>
</div>
@endsection
