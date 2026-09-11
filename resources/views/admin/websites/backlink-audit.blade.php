@extends('layouts.app')

@section('content')
<div class="space-y-6">
    <header class="space-y-3">
        <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'backlinks']) }}" class="text-sm font-medium text-slate-600 hover:underline">← Back to backlinks</a>
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div><h1 class="text-2xl font-semibold tracking-tight text-slate-950">Backlink audit</h1><p class="mt-1 break-all text-sm text-slate-600">{{ $audit->domain }} · {{ ucfirst(str_replace('_', ' ', $audit->status)) }}</p></div>
            @if ($canManageWebsite)
                <form method="POST" action="{{ route('admin.backlink-audits.store', $website) }}">@csrf
                    @foreach ($audit->competitors as $competitor)<input type="hidden" name="competitor_ids[]" value="{{ $competitor->website_competitor_id }}">@endforeach
                    <button class="min-h-11 rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white">{{ $audit->status === 'failed' ? 'Retry audit' : 'Refresh audit' }}</button>
                </form>
            @endif
        </div>
        <p class="text-sm text-slate-500">DataForSEO observations and estimates collected {{ $audit->started_at?->format('j M Y, H:i') ?? 'when processing starts' }}. These are not Search Console measurements, and links do not prove ranking causation.</p>
        <p class="text-sm text-slate-500">Limits: {{ number_format($audit->limits['current_links']) }} current links, {{ number_format($audit->limits['lost_links']) }} lost links, {{ number_format($audit->limits['linked_pages']) }} own pages, {{ number_format($audit->limits['gap_domains']) }} gap domains, and {{ number_format($audit->limits['analyse_pages']) }} page analyses. Recorded provider cost: USD {{ number_format((float) $cost, 4) }}.</p>
        @if ($audit->competitors->isNotEmpty())<p class="text-sm text-slate-600">Compared with: {{ $audit->competitors->pluck('domain')->implode(', ') }}</p>@endif
        @if (in_array($audit->status, ['pending', 'processing']))<p role="status" class="rounded-md bg-teal-50 p-3 text-sm text-teal-900">Audit in progress. {{ count($audit->stages ?? []) }} of {{ count(\App\Services\BacklinkAuditService::STAGES) }} stages saved. Refresh this page to see newly stored evidence.</p>@endif
        @foreach ($audit->errors ?? [] as $stage => $error)<p role="alert" class="rounded-md bg-amber-50 p-3 text-sm text-amber-900">{{ ucfirst(str_replace('_', ' ', $stage)) }}: {{ $error }}</p>@endforeach
    </header>

    @php($overview = $audit->overview ?? [])
    <section class="overflow-hidden rounded-xl border bg-white shadow-sm" aria-labelledby="backlink-overview-heading">
        <div class="border-b p-4 sm:p-6"><h2 id="backlink-overview-heading" class="text-lg font-semibold text-slate-950">Overview</h2><p class="mt-1 text-sm text-slate-600">Profile size and quality indicators from the provider sample.</p></div>
        <dl class="grid grid-cols-2 gap-px bg-slate-200 lg:grid-cols-4">
            @foreach (['backlinks' => 'Backlinks', 'referring_domains' => 'Referring domains', 'domain_rank' => 'Domain rank', 'broken_backlinks' => 'Broken links', 'dofollow' => 'Dofollow', 'nofollow' => 'Nofollow', 'spam_score' => 'Spam score', 'largest_domain_share' => 'Largest domain share %'] as $key => $label)
                <div class="bg-white p-4"><dt class="text-sm font-medium text-slate-600">{{ $label }}</dt><dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ isset($overview[$key]) ? number_format($overview[$key]) : '—' }}</dd></div>
            @endforeach
        </dl>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="backlink-opportunities-heading">
        <h2 id="backlink-opportunities-heading" class="text-lg font-semibold text-slate-950">Prioritised opportunities</h2>
        <p class="mt-1 text-sm text-slate-600">Observations are separated from hypotheses. Content recommendations require original work and verified business facts.</p>
        <div class="mt-4 space-y-4">
            @forelse ($audit->opportunities as $opportunity)
                <article class="rounded-lg border p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div><p class="text-xs font-semibold uppercase tracking-wide text-teal-700">{{ str_replace('_', ' ', $opportunity->type) }} · Priority {{ $opportunity->priority_score }}</p><h3 class="mt-1 font-semibold text-slate-950">{{ $opportunity->title }}</h3><p class="mt-1 text-sm text-slate-600">{{ $opportunity->evidence['user_need'] ?? $opportunity->evidence['relevance_reason'] ?? '' }}</p></div>
                        @if ($opportunity->type === 'content' && $opportunity->content_request_id)<span class="text-sm font-medium text-teal-700">Queued in Content</span>
                        @elseif ($opportunity->type === 'content' && $canManageWebsite)<form method="POST" action="{{ route('admin.backlink-opportunities.queue', [$website, $opportunity]) }}">@csrf<button class="min-h-11 rounded-md bg-slate-950 px-4 py-2 text-sm font-medium text-white">Queue content brief</button></form>@endif
                    </div>
                    <div class="mt-4 grid gap-4 md:grid-cols-2">
                        @foreach (['observations' => 'Observed evidence', 'hypotheses' => 'Hypotheses', 'improvements' => 'Original improvements', 'outline' => 'Suggested outline'] as $key => $label)
                            @if (! empty($opportunity->evidence[$key]))<div><h4 class="text-sm font-medium text-slate-900">{{ $label }}</h4><ul class="mt-2 list-disc space-y-1 pl-5 text-sm text-slate-600">@foreach ($opportunity->evidence[$key] as $item)<li>{{ $item }}</li>@endforeach</ul></div>@endif
                        @endforeach
                    </div>
                    @foreach ($opportunity->evidence['source_urls'] ?? array_filter([$opportunity->evidence['source_url'] ?? null]) as $url)<a href="{{ $url }}" target="_blank" rel="noopener noreferrer" class="mt-3 block break-all text-sm text-teal-700 hover:underline">{{ $url }}</a>@endforeach
                </article>
            @empty
                <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No actionable opportunities have been produced from the available evidence yet.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="new-lost-heading">
        <h2 id="new-lost-heading" class="text-lg font-semibold">New and lost trend</h2><p class="mt-1 text-sm text-slate-600">Monthly provider observations over the configured twelve-month window.</p>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b"><th class="p-3 text-left">Month</th><th class="p-3 text-right">New links</th><th class="p-3 text-right">Lost links</th><th class="p-3 text-right">New domains</th><th class="p-3 text-right">Lost domains</th></tr></thead><tbody>
            @forelse ($audit->new_lost_trend ?? [] as $row)<tr class="border-b border-slate-100"><td class="p-3">{{ isset($row['date']) ? \Illuminate\Support\Carbon::parse($row['date'])->format('M Y') : '—' }}</td><td class="p-3 text-right tabular-nums">{{ number_format($row['new_backlinks']) }}</td><td class="p-3 text-right tabular-nums">{{ number_format($row['lost_backlinks']) }}</td><td class="p-3 text-right tabular-nums">{{ number_format($row['new_referring_domains']) }}</td><td class="p-3 text-right tabular-nums">{{ number_format($row['lost_referring_domains']) }}</td></tr>
            @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Trend data is unavailable.</td></tr>@endforelse
        </tbody></table></div>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="links-heading">
        <h2 id="links-heading" class="text-lg font-semibold">Backlinks</h2>
        <nav class="my-4 flex flex-wrap gap-2" aria-label="Backlink filters">@foreach (['all' => 'All', 'live' => 'Live', 'lost' => 'Lost', 'broken' => 'Broken'] as $value => $label)<a href="{{ route('admin.backlink-audits.show', [$website, $audit, 'links' => $value]) }}" @if ($linkFilter === $value) aria-current="page" @endif class="rounded-md border px-3 py-2 text-sm aria-[current=page]:bg-slate-100">{{ $label }}</a>@endforeach</nav>
        <div class="overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">Source</th><th class="p-3">Destination / anchor</th><th class="p-3">State</th><th class="p-3 text-right">Rank</th><th class="p-3">First / last seen</th></tr></thead><tbody>
            @forelse ($links as $link)<tr class="border-b border-slate-100"><td class="min-w-64 p-3"><span class="font-medium">{{ $link->source_domain }}</span><a href="{{ $link->source_url }}" target="_blank" rel="noopener noreferrer" class="mt-1 block max-w-80 truncate text-xs text-teal-700">{{ $link->source_url }}</a></td><td class="min-w-64 p-3"><a href="{{ $link->target_url }}" target="_blank" rel="noopener noreferrer" class="block max-w-80 truncate text-teal-700">{{ $link->target_url }}</a><span class="mt-1 block text-xs text-slate-500">{{ $link->anchor ?: 'No anchor supplied' }} · {{ $link->dofollow === null ? 'Attribute unknown' : ($link->dofollow ? 'Dofollow' : 'Nofollow') }}</span></td><td class="p-3">{{ $link->broken ? 'Broken' : ucfirst($link->state) }}</td><td class="p-3 text-right tabular-nums">{{ $link->source_domain_rank ?? '—' }}</td><td class="p-3 whitespace-nowrap text-xs text-slate-600">{{ $link->first_seen?->format('j M Y') ?? '—' }}<br>{{ $link->last_seen?->format('j M Y') ?? '—' }}</td></tr>
            @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">No links in this sample.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $links->links() }}</div>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="pages-heading">
        <h2 id="pages-heading" class="text-lg font-semibold">Linked pages</h2><p class="mt-1 text-sm text-slate-600">Own pages appear first, followed by sampled competitor pages. Only the strongest five competitor candidates are fetched for analysis.</p>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">Page</th><th class="p-3">Type</th><th class="p-3 text-right">Links</th><th class="p-3 text-right">Domains</th><th class="p-3">Analysis</th></tr></thead><tbody>
            @forelse ($pages as $page)<tr class="border-b border-slate-100"><td class="min-w-80 p-3"><a href="{{ $page->url }}" target="_blank" rel="noopener noreferrer" class="block max-w-xl truncate font-medium text-teal-700">{{ $page->url }}</a><span class="text-xs text-slate-500">{{ $page->domain }}</span></td><td class="p-3">{{ ucfirst($page->kind) }}</td><td class="p-3 text-right tabular-nums">{{ number_format($page->backlinks) }}</td><td class="p-3 text-right tabular-nums">{{ number_format($page->referring_domains) }}</td><td class="min-w-64 p-3">{{ ucfirst(str_replace('_', ' ', $page->status)) }}@if ($page->error)<span class="block text-xs text-amber-800">{{ $page->error }}</span>@endif @if ($page->analysis)<details class="mt-2"><summary class="cursor-pointer font-medium text-teal-700">View page analysis</summary><div class="mt-2 space-y-2 text-xs text-slate-600"><p class="font-medium text-slate-800">{{ $page->analysis['title'] ?? 'Untitled page' }}</p><p>{{ $page->analysis['meta_description'] ?? '' }}</p><p>Structured data: {{ implode(', ', $page->analysis['structured_data_types'] ?? []) ?: 'None observed' }}</p><ul>@foreach (array_slice($page->analysis['headings'] ?? [], 0, 10) as $heading)<li>{{ strtoupper($heading['level']) }}: {{ $heading['text'] }}</li>@endforeach</ul><details><summary class="cursor-pointer">Extracted main content</summary><p class="mt-1 whitespace-pre-line">{{ $page->analysis['main_content'] ?? '' }}</p></details></div></details>@endif</td></tr>
            @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">No linked pages in this sample.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $pages->links() }}</div>
    </section>

    <section class="rounded-xl border bg-white p-4 shadow-sm sm:p-6" aria-labelledby="gaps-heading">
        <h2 id="gaps-heading" class="text-lg font-semibold">Competitor link gaps</h2><p class="mt-1 text-sm text-slate-600">Domains observed linking to selected competitors and not this website. Relevance still requires human review.</p>
        <div class="mt-4 overflow-x-auto"><table class="min-w-full text-sm"><thead><tr class="border-b text-left"><th class="p-3">Domain</th><th class="p-3 text-right">Rank</th><th class="p-3 text-right">Competitors</th><th class="p-3 text-right">Priority</th><th class="p-3 text-right">Action</th></tr></thead><tbody>
            @forelse ($gaps as $gap)<tr class="border-b border-slate-100"><td class="p-3"><span class="font-medium">{{ $gap->domain }}</span><span class="mt-1 block text-xs text-slate-500">Links observed to: {{ collect($gap->competitor_evidence)->filter(fn ($item) => (int) ($item['backlinks'] ?? $item['referring_pages'] ?? 0) > 0)->keys()->implode(', ') ?: 'competitor sample' }}</span></td><td class="p-3 text-right">{{ $gap->domain_rank ?? '—' }}</td><td class="p-3 text-right">{{ $gap->competitor_count }}</td><td class="p-3 text-right">{{ $gap->priority_score }}</td><td class="p-3 text-right">@if ($gap->prospect)<a href="{{ route('admin.prospects.show', $gap->prospect) }}" class="font-medium text-teal-700 hover:underline">View in Outreach</a>@elseif ($canAccessOutreach)<form method="POST" action="{{ route('admin.backlink-domain-gaps.outreach', [$website, $gap]) }}">@csrf<button class="min-h-11 rounded-md border px-3 py-2 font-medium">Save to Outreach</button></form>@else<span class="text-slate-400">Review only</span>@endif</td></tr>
            @empty<tr><td colspan="5" class="p-6 text-center text-slate-500">Choose competitors when running the audit to find link gaps.</td></tr>@endforelse
        </tbody></table></div><div class="mt-4">{{ $gaps->links() }}</div>
    </section>
</div>
@endsection
