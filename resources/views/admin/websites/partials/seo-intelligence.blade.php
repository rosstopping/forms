<div id="website-panel-seo" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-seo" data-tab-panel="seo" hidden>
    <section class="overflow-hidden rounded-xl border bg-white shadow-sm" aria-labelledby="seo-intelligence-title">
        <div class="@container border-b border-slate-200 p-4">
        <div class="flex flex-col gap-4 @xl:flex-row @xl:items-start @xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Third-party market intelligence</p>
                    <p class="rounded-full bg-teal-50 px-2 py-1 text-xs font-medium text-teal-800 ring-1 ring-teal-700/10">DataForSEO estimate</p>
                </div>
                <h2 id="seo-intelligence-title" class="mt-2 text-lg font-semibold text-slate-950">SEO Intelligence</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">Organic visibility estimates for {{ $website->primaryDomain()?->domain ?: 'this website' }}. These figures are separate from Google Search Console performance.</p>
            </div>

            @if ($canManageWebsite)
                <div class="shrink-0">
                    @if ($dataForSeoConfigured)
                        <form method="POST" action="{{ route('admin.seo-intelligence.store', $website) }}">
                            @csrf
                            <button type="submit" @disabled($seoGeneration && in_array($seoGeneration->status, ['pending', 'processing'], true)) class="rounded-lg border border-slate-950/10 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm shadow-slate-950/5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                                {{ $seoSnapshot ? 'Refresh intelligence' : 'Generate intelligence' }}
                            </button>
                        </form>
                    @else
                        <p class="max-w-xs text-sm text-amber-700">Add the DataForSEO credentials to Sitewell before generating intelligence.</p>
                    @endif
                </div>
            @endif
        </div>
        </div>

        @if ($seoGeneration && in_array($seoGeneration->status, ['pending', 'processing'], true))
            <div class="border-b border-blue-200 bg-blue-50 px-4 py-3">
                <p class="text-sm font-medium text-blue-900">SEO intelligence is {{ $seoGeneration->status === 'pending' ? 'queued' : 'being generated' }}.</p>
                <p class="mt-1 text-sm text-blue-700">This page remains available while the background job runs. Refresh shortly to see the results.</p>
            </div>
        @elseif ($seoGeneration?->status === 'failed')
            <div class="border-b border-red-200 bg-red-50 px-4 py-3">
                <p class="text-sm font-medium text-red-900">The latest SEO intelligence refresh failed.</p>
                <p class="mt-1 text-sm text-red-700">Existing successful data is retained. You can try again when the provider is available.</p>
            </div>
        @endif

        @if ($seoSnapshot)
            <div class="@container">
                <dl class="grid grid-cols-2 gap-px bg-slate-200 @3xl:grid-cols-4">
                    <div class="bg-white p-4">
                        <dt class="truncate text-sm text-slate-500">Ranking keywords</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ number_format($seoSnapshot->organic_keywords) }}</dd>
                    </div>
                    <div class="bg-white p-4">
                        <dt class="truncate text-sm text-slate-500">Page-one keywords</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ number_format($seoSnapshot->top_10_keywords) }}</dd>
                    </div>
                    <div class="bg-white p-4">
                        <dt class="truncate text-sm text-slate-500">Positions 11–20</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-amber-700">{{ number_format(max(0, $seoSnapshot->top_20_keywords - $seoSnapshot->top_10_keywords)) }}</dd>
                    </div>
                    <div class="bg-white p-4">
                        <dt class="truncate text-sm text-slate-500">Estimated monthly visits</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">~{{ number_format((float) $seoSnapshot->estimated_organic_traffic) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="@container border-t border-slate-200">
            <div class="grid gap-px bg-slate-200 @3xl:grid-cols-[2fr_1fr]">
                <div class="bg-white p-4">
                    <p class="text-xs font-medium uppercase tracking-widest text-slate-500">What can we improve?</p>
                    <h3 class="mt-2 text-base font-semibold text-slate-950">{{ number_format($strikingDistanceCount) }} striking-distance keywords</h3>
                    <p class="mt-1 text-sm text-slate-600">Keywords currently ranking in positions 4–20 may offer the clearest near-term optimisation opportunities.</p>
                    <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_filter' => 'positions_11_20', 'seo_sort' => 'search_volume', 'seo_direction' => 'desc']) }}" class="mt-3 inline-flex text-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">Review page-two keywords</a>
                </div>
                <div class="bg-slate-50 p-4">
                    <p class="text-sm font-medium text-slate-900">Snapshot details</p>
                    <dl class="mt-3 grid grid-cols-[auto_1fr] gap-x-4 gap-y-2 text-sm">
                        <dt class="text-slate-500">Observed</dt>
                        <dd class="text-right font-medium text-slate-800">{{ $seoSnapshot->completed_at?->format('j M Y, H:i') }}</dd>
                        <dt class="text-slate-500">Location</dt>
                        <dd class="text-right font-medium tabular-nums text-slate-800">{{ $seoSnapshot->location_code }}</dd>
                        <dt class="text-slate-500">Language</dt>
                        <dd class="text-right font-medium uppercase text-slate-800">{{ $seoSnapshot->language_code }}</dd>
                    </dl>
                </div>
            </div>
            </div>
        @else
            <div class="p-8 text-center">
                <h3 class="text-base font-semibold text-slate-950">No SEO snapshot yet</h3>
                <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600">Generate the first snapshot to discover estimated organic rankings and search opportunities. The website does not need to be online.</p>
            </div>
        @endif
    </section>

    @if ($seoSnapshot)
        <section class="rounded-xl border bg-white shadow-sm" aria-labelledby="ranking-keywords-title">
            <div class="@container border-b border-slate-200 p-4">
            <div class="flex flex-col gap-4 @4xl:flex-row @4xl:items-end @4xl:justify-between">
                <div>
                    <h3 id="ranking-keywords-title" class="text-base font-semibold text-slate-950">Ranking keywords</h3>
                    <p class="mt-1 text-sm text-slate-600">Locally stored DataForSEO estimates from this snapshot.</p>
                </div>
                <form method="GET" action="{{ route('admin.websites.show', $website) }}" class="grid gap-3 @md:grid-cols-3">
                    <input type="hidden" name="tab" value="seo">
                    <div>
                        <label for="seo_filter">Filter</label>
                        <select id="seo_filter" name="seo_filter">
                            @foreach (['all' => 'All keywords', 'top_3' => 'Top 3', 'page_1' => 'Page 1', 'positions_11_20' => 'Positions 11–20', 'positions_21_50' => 'Positions 21–50', 'positions_51_100' => 'Positions 51–100', 'commercial' => 'Commercial intent'] as $value => $label)
                                <option value="{{ $value }}" @selected($seoFilter === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label for="seo_sort">Sort by</label>
                        <select id="seo_sort" name="seo_sort">
                            @foreach (['position' => 'Position', 'search_volume' => 'Search volume', 'estimated_traffic' => 'Estimated traffic', 'cpc' => 'CPC'] as $value => $label)
                                <option value="{{ $value }}" @selected($seoSort === $value)>{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="flex items-end gap-2">
                        <input type="hidden" name="seo_direction" value="{{ $seoSort === 'position' ? 'asc' : 'desc' }}">
                        <button type="submit" class="rounded-lg border border-slate-950/10 bg-white px-3 py-2.5 text-sm font-medium text-slate-700 shadow-sm shadow-slate-950/5 hover:bg-slate-50">Apply</button>
                    </div>
                </form>
            </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-200">
                    <thead>
                        <tr>
                            <th class="px-4 py-3">Keyword</th>
                            <th class="px-4 py-3 text-right">Position</th>
                            <th class="px-4 py-3 text-right">Previous</th>
                            <th class="px-4 py-3 text-right">Volume</th>
                            <th class="px-4 py-3 text-right">Traffic</th>
                            <th class="px-4 py-3 text-right">CPC</th>
                            <th class="px-4 py-3">Intent</th>
                            <th class="px-4 py-3 text-right">Difficulty</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($seoKeywords as $keyword)
                            <tr>
                                <td class="max-w-sm px-4 py-3">
                                    <p class="truncate font-medium text-slate-950" title="{{ $keyword->keyword }}">{{ $keyword->keyword }}</p>
                                    @if ($keyword->ranking_url)
                                        <p class="mt-1 truncate text-xs text-slate-500" title="{{ $keyword->ranking_url }}"><a href="{{ $keyword->ranking_url }}" target="_blank" rel="noopener noreferrer" class="hover:text-teal-700">{{ \Illuminate\Support\Str::after($keyword->ranking_url, '://') }}</a></p>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right font-semibold tabular-nums text-slate-950">{{ $keyword->position }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-500">{{ $keyword->previous_position ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ is_null($keyword->search_volume) ? '—' : number_format($keyword->search_volume) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ is_null($keyword->estimated_traffic) ? '—' : number_format((float) $keyword->estimated_traffic, 1) }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ is_null($keyword->cpc) ? '—' : '$'.number_format((float) $keyword->cpc, 2) }}</td>
                                <td class="px-4 py-3 capitalize text-slate-600">{{ $keyword->search_intent ?: '—' }}</td>
                                <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $keyword->keyword_difficulty ?? '—' }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="8" class="px-4 py-10 text-center text-slate-500">No keywords match this filter.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            @if ($seoKeywords->hasPages())
                <div class="border-t border-slate-200 p-4">{{ $seoKeywords->links() }}</div>
            @endif
        </section>
    @endif
</div>
