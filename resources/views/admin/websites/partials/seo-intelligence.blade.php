<div id="website-panel-seo" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-seo" data-tab-panel="seo" hidden>
    <div class="space-y-6" data-tabs data-tabs-key="seo-intelligence" data-default-tab="{{ request('seo_section', request()->has('seo_filter') ? 'keywords' : 'overview') }}">
        <div class="max-w-full overflow-x-auto border-b border-slate-950/10" role="tablist" aria-label="SEO Intelligence sections">
            <div class="flex min-w-max gap-1 pb-2">
                <button type="button" id="seo-section-tab-overview" class="shrink-0 rounded-md px-3 py-2 text-base font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 aria-selected:bg-slate-100 aria-selected:text-slate-950 sm:text-sm" role="tab" aria-selected="true" aria-controls="seo-section-panel-overview" tabindex="0" data-tab="overview">Overview</button>
                @if ($seoSnapshot)
                    <button type="button" id="seo-section-tab-actions" class="shrink-0 rounded-md px-3 py-2 text-base font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 aria-selected:bg-slate-100 aria-selected:text-slate-950 sm:text-sm" role="tab" aria-selected="false" aria-controls="seo-section-panel-actions" tabindex="-1" data-tab="actions">Recommended Actions</button>
                    <button type="button" id="seo-section-tab-keywords" class="shrink-0 rounded-md px-3 py-2 text-base font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 aria-selected:bg-slate-100 aria-selected:text-slate-950 sm:text-sm" role="tab" aria-selected="false" aria-controls="seo-section-panel-keywords" tabindex="-1" data-tab="keywords">Keywords</button>
                    <button type="button" id="seo-section-tab-backlinks" class="shrink-0 rounded-md px-3 py-2 text-base font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 aria-selected:bg-slate-100 aria-selected:text-slate-950 sm:text-sm" role="tab" aria-selected="false" aria-controls="seo-section-panel-backlinks" tabindex="-1" data-tab="backlinks">Backlinks</button>
                    <button type="button" id="seo-section-tab-competitors" class="shrink-0 rounded-md px-3 py-2 text-base font-medium text-slate-500 hover:bg-slate-100 hover:text-slate-900 aria-selected:bg-slate-100 aria-selected:text-slate-950 sm:text-sm" role="tab" aria-selected="false" aria-controls="seo-section-panel-competitors" tabindex="-1" data-tab="competitors">Competitors</button>
                @endif
            </div>
        </div>

        <div id="seo-section-panel-overview" role="tabpanel" aria-labelledby="seo-section-tab-overview" data-tab-panel="overview">
    <section class="overflow-hidden rounded-xl border bg-white shadow-sm" aria-labelledby="seo-intelligence-title">
        <div class="@container border-b border-slate-200 p-4">
        <div class="flex flex-col gap-4 @xl:flex-row @xl:items-start @xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Third-party market intelligence</p>
                    <p class="rounded-full bg-teal-50 px-2 py-1 text-xs font-medium text-teal-800 ring-1 ring-teal-700/10">Estimated data</p>
                </div>
                <h2 id="seo-intelligence-title" class="mt-2 text-lg font-semibold text-slate-950">SEO Intelligence</h2>
                <p class="mt-1 max-w-3xl text-sm text-slate-600">Organic visibility estimates for {{ $website->primaryDomain()?->domain ?: 'this website' }}. These figures are separate from Google Search Console performance.</p>
            </div>

            @if ($canManageWebsite)
                <div class="flex shrink-0 flex-col items-end gap-2">
                    @if ($dataForSeoConfigured)
                        <form method="POST" action="{{ route('admin.seo-intelligence.store', $website) }}">
                            @csrf
                            <button type="submit" @disabled($seoGeneration && in_array($seoGeneration->status, ['pending', 'processing'], true)) class="rounded-lg border border-slate-950/10 bg-white px-3 py-2 text-sm font-medium text-slate-700 shadow-sm shadow-slate-950/5 hover:bg-slate-50 disabled:cursor-not-allowed disabled:bg-slate-100 disabled:text-slate-400">
                                {{ $seoSnapshot ? 'Refresh intelligence' : 'Generate intelligence' }}
                            </button>
                        </form>
                    @else
                        <p class="max-w-xs text-sm text-amber-700">Add the SEO data provider credentials to Sitewell before generating intelligence.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.seo-snapshot-settings.update', $website) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <input type="hidden" name="seo_weekly_snapshots_enabled" value="0">
                        <input id="seo-weekly-snapshots" type="checkbox" name="seo_weekly_snapshots_enabled" value="1" @checked($website->seo_weekly_snapshots_enabled)>
                        <label for="seo-weekly-snapshots" class="text-sm text-slate-600">Automatic weekly snapshots</label>
                        <button class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Save</button>
                    </form>
                    <p class="max-w-sm text-right text-xs text-slate-500">Uses the paid SEO data API. Enabling it also imports available monthly history once.</p>
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
                    <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'keywords', 'seo_filter' => 'positions_11_20', 'seo_sort' => 'search_volume', 'seo_direction' => 'desc']) }}" class="mt-3 inline-flex text-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">Review page-two keywords</a>
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
            <div class="grid gap-4 border-t border-slate-200 p-4 lg:grid-cols-2">
                <x-progress-chart title="Estimated organic traffic" description="Monthly third-party organic visibility estimate." :points="$seoHistory" value-key="estimated_organic_traffic" format="traffic" />
                <x-progress-chart title="Ranking keywords" description="Keywords estimated to rank in Google's top 100." :points="$seoHistory" value-key="organic_keywords" />
            </div>
        @else
            <div class="p-8 text-center">
                <h3 class="text-base font-semibold text-slate-950">No SEO snapshot yet</h3>
                <p class="mx-auto mt-2 max-w-lg text-sm text-slate-600">Generate the first snapshot to discover estimated organic rankings and search opportunities. The website does not need to be online.</p>
            </div>
        @endif
    </section>
        </div>

    @if ($seoSnapshot)
        <div id="seo-section-panel-actions" role="tabpanel" aria-labelledby="seo-section-tab-actions" data-tab-panel="actions" hidden>
        <section class="rounded-xl border bg-white shadow-sm" aria-labelledby="seo-opportunities-title">
            <div class="border-b border-slate-950/10 p-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div class="min-w-0">
                        <h3 id="seo-opportunities-title" class="text-balance text-base font-semibold text-slate-950">Recommended actions</h3>
                        <p class="max-w-[72ch] text-pretty text-base text-slate-600 sm:text-sm">Prioritised from third-party ranking estimates. Review each recommendation before changing the website.</p>
                    </div>
                    <p class="shrink-0 text-base tabular-nums text-slate-500 sm:text-sm">{{ number_format($seoOpportunities->count()) }} shown</p>
                </div>
            </div>

            <div class="divide-y divide-slate-950/10">
                @forelse ($seoOpportunities as $opportunity)
                    @php($metrics = $opportunity->metrics ?? [])
                    <article class="@container p-4">
                        <div class="grid gap-4 @3xl:grid-cols-[2fr_3fr]">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="rounded-full bg-teal-50 px-2 py-1 font-medium text-teal-800 ring-1 ring-teal-700/10">{{ str($opportunity->type)->headline() }}</p>
                                    <p class="tabular-nums text-slate-500">Priority {{ number_format((float) $opportunity->priority_score) }}/100</p>
                                    @if ($opportunity->status === 'queued')
                                        <p class="rounded-full bg-slate-100 px-2 py-1 font-medium text-slate-700">Added to content todos</p>
                                    @endif
                                </div>
                                <h4 class="mt-3 text-balance font-semibold text-slate-950">{{ $opportunity->title }}</h4>
                                <p class="mt-1 text-pretty text-base text-slate-600 sm:text-sm">{{ $opportunity->summary }}</p>
                                <dl class="mt-3 flex flex-wrap gap-x-5 gap-y-2 text-base sm:text-sm">
                                    <div>
                                        <dt class="font-medium text-slate-700">Position</dt>
                                        <dd class="tabular-nums text-slate-500">{{ data_get($metrics, 'position', '—') }}</dd>
                                    </div>
                                    <div>
                                        <dt class="font-medium text-slate-700">Volume</dt>
                                        <dd class="tabular-nums text-slate-500">{{ is_null(data_get($metrics, 'search_volume')) ? '—' : number_format(data_get($metrics, 'search_volume')) }}</dd>
                                    </div>
                                    @if (! is_null(data_get($metrics, 'position_change')))
                                        <div>
                                            <dt class="font-medium text-slate-700">Change</dt>
                                            <dd class="tabular-nums {{ data_get($metrics, 'position_change') > 0 ? 'text-emerald-700' : 'text-red-700' }}">{{ data_get($metrics, 'position_change') > 0 ? '+' : '' }}{{ data_get($metrics, 'position_change') }}</dd>
                                        </div>
                                    @endif
                                    @if (data_get($metrics, 'search_intent'))
                                        <div>
                                            <dt class="font-medium text-slate-700">Intent</dt>
                                            <dd class="capitalize text-slate-500">{{ data_get($metrics, 'search_intent') }}</dd>
                                        </div>
                                    @endif
                                </dl>
                            </div>

                            <div class="rounded-lg bg-slate-50 p-4">
                                <h5 class="font-medium text-slate-950">Recommended action</h5>
                                <p class="mt-1 text-pretty text-base text-slate-600 sm:text-sm">{{ $opportunity->recommendation }}</p>
                                @if (data_get($metrics, 'ranking_url'))
                                    <div class="mt-3 text-base font-medium sm:text-sm">
                                        <a href="{{ data_get($metrics, 'ranking_url') }}" target="_blank" rel="noopener noreferrer" class="text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">Open ranking page</a>
                                    </div>
                                @endif
                                @if ($canManageWebsite && $opportunity->status === 'open')
                                    <div class="mt-4">
                                        @if ($website->repository)
                                            <form method="POST" action="{{ route('admin.seo-opportunities.queue', [$website, $opportunity]) }}">
                                                @csrf
                                                <button type="submit" class="relative rounded-md border border-slate-950/10 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                                                    Add to action list
                                                    <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
                                                </button>
                                            </form>
                                        @else
                                            <a href="{{ route('admin.website-repositories.create', $website) }}" class="relative inline-flex rounded-md border border-slate-950/10 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600">
                                                Connect GitHub to add todo
                                                <span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span>
                                            </a>
                                        @endif
                                    </div>
                                @endif
                            </div>
                        </div>
                    </article>
                @empty
                    <div class="p-8 text-center">
                        <h4 class="text-balance font-semibold text-slate-950">No priority actions found</h4>
                        <p class="mx-auto mt-2 max-w-[60ch] text-pretty text-base text-slate-600 sm:text-sm">The stored keyword sample does not currently meet the configured opportunity thresholds. Future snapshots may reveal ranking movement.</p>
                    </div>
                @endforelse
            </div>
        </section>
        </div>

        <div id="seo-section-panel-competitors" role="tabpanel" aria-labelledby="seo-section-tab-competitors" data-tab-panel="competitors" hidden>
        <section class="rounded-xl border bg-white shadow-sm" aria-labelledby="organic-competitors-title">
            <div class="border-b border-slate-950/10 p-4">
                <h3 id="organic-competitors-title" class="text-balance text-base font-semibold text-slate-950">Organic competitors</h3>
                <p class="mt-1 text-pretty text-base text-slate-600 sm:text-sm">Domains appearing alongside this website for the same organic searches.</p>
            </div>

            @if (isset($seoSnapshot->errors['organic_competitors']))
                <div class="border-b border-amber-950/10 bg-amber-50 p-4">
                    <p class="text-base text-amber-800 sm:text-sm">Organic competitor data was unavailable when this snapshot was generated. Other successful SEO data has been retained.</p>
                </div>
            @endif

            <div class="p-4">
                <div class="-mx-4 -my-2 overflow-x-auto whitespace-nowrap">
                    <div class="inline-block min-w-full px-4 py-2 align-middle">
                        <table class="w-full divide-y divide-slate-950/10">
                            <thead>
                                <tr>
                                    <th class="whitespace-nowrap py-3 pr-4 text-left">Domain</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Shared keywords</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Ranking keywords</th>
                                    <th class="whitespace-nowrap py-3 pl-4 text-right">Estimated visits</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-950/5">
                                @forelse ($seoCompetitors as $competitor)
                                    <tr>
                                        <td class="py-3 pr-4 font-medium text-slate-950">{{ $competitor->domain }}</td>
                                        <td class="px-4 py-3 text-right font-medium tabular-nums text-teal-700">{{ number_format($competitor->common_keywords) }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ is_null($competitor->organic_keywords) ? '—' : number_format($competitor->organic_keywords) }}</td>
                                        <td class="py-3 pl-4 text-right tabular-nums text-slate-700">{{ is_null($competitor->estimated_traffic) ? '—' : '~'.number_format((float) $competitor->estimated_traffic) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-8 text-center text-slate-500">No organic competitors were returned for this snapshot.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        </div>

        <div id="seo-section-panel-backlinks" role="tabpanel" aria-labelledby="seo-section-tab-backlinks" data-tab-panel="backlinks" hidden>
        <section class="rounded-xl border bg-white shadow-sm" aria-labelledby="backlinks-title">
            <div class="border-b border-slate-950/10 p-4">
                <h3 id="backlinks-title" class="text-balance text-base font-semibold text-slate-950">Backlinks</h3>
                <p class="mt-1 text-pretty text-base text-slate-600 sm:text-sm">Who links to this website, based on locally stored third-party estimates.</p>
            </div>

            @if (isset($seoSnapshot->errors['backlink_overview']) || isset($seoSnapshot->errors['referring_domains']))
                <div class="border-b border-amber-950/10 bg-amber-50 p-4">
                    <p class="text-base text-amber-800 sm:text-sm">Some backlink data was unavailable when this snapshot was generated. The successful SEO data has been retained.</p>
                </div>
            @endif

            <div class="@container p-4">
                <dl class="grid grid-cols-2 gap-y-5 @2xl:grid-cols-4">
                    <div class="pr-4">
                        <dt class="truncate font-medium text-slate-700">Backlinks</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ is_null($seoSnapshot->backlinks) ? '—' : number_format($seoSnapshot->backlinks) }}</dd>
                    </div>
                    <div class="border-l border-slate-950/10 pl-4 @2xl:pr-4">
                        <dt class="truncate font-medium text-slate-700">Referring domains</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ is_null($seoSnapshot->referring_domains) ? '—' : number_format($seoSnapshot->referring_domains) }}</dd>
                    </div>
                    <div class="pr-4 @2xl:border-l @2xl:border-slate-950/10 @2xl:pl-4">
                        <dt class="truncate font-medium text-slate-700">Domain rank</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ $seoSnapshot->domain_rank ?? '—' }}</dd>
                    </div>
                    <div class="border-l border-slate-950/10 pl-4">
                        <dt class="truncate font-medium text-slate-700">Broken backlinks</dt>
                        <dd class="mt-1 text-2xl font-semibold tabular-nums text-slate-950">{{ is_null($seoSnapshot->broken_backlinks) ? '—' : number_format($seoSnapshot->broken_backlinks) }}</dd>
                    </div>
                </dl>
            </div>

            <div class="border-t border-slate-950/10 p-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h4 class="text-balance font-semibold text-slate-950">Strongest referring domains</h4>
                        <p class="text-pretty text-base text-slate-600 sm:text-sm">The highest-ranked domains in the stored sample.</p>
                    </div>
                    @if ($seoSnapshot->referring_domains > $seoReferringDomains->count())
                        <p class="text-base tabular-nums text-slate-500 sm:text-sm">Showing {{ number_format($seoReferringDomains->count()) }} of {{ number_format($seoSnapshot->referring_domains) }}</p>
                    @endif
                </div>

                <div class="-mx-4 -my-2 mt-4 overflow-x-auto whitespace-nowrap">
                    <div class="inline-block min-w-full px-4 py-2 align-middle">
                        <table class="w-full divide-y divide-slate-950/10">
                            <thead>
                                <tr>
                                    <th class="whitespace-nowrap py-3 pr-4 text-left">Domain</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Domain rank</th>
                                    <th class="whitespace-nowrap py-3 pl-4 text-right">Backlinks</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-950/5">
                                @forelse ($seoReferringDomains as $referringDomain)
                                    <tr>
                                        <td class="py-3 pr-4 font-medium text-slate-950">{{ $referringDomain->domain }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $referringDomain->domain_rank ?? '—' }}</td>
                                        <td class="py-3 pl-4 text-right tabular-nums text-slate-700">{{ number_format($referringDomain->backlinks_count) }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="3" class="py-8 text-center text-slate-500">No referring domains were returned for this snapshot.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
        </div>

        <div id="seo-section-panel-keywords" role="tabpanel" aria-labelledby="seo-section-tab-keywords" data-tab-panel="keywords" hidden>
        <section class="rounded-xl border bg-white shadow-sm" aria-labelledby="ranking-keywords-title">
            <div class="@container border-b border-slate-200 p-4">
            <div class="flex flex-col gap-4 @4xl:flex-row @4xl:items-end @4xl:justify-between">
                <div>
                    <h3 id="ranking-keywords-title" class="text-base font-semibold text-slate-950">Ranking keywords</h3>
                    <p class="mt-1 text-sm text-slate-600">Locally stored third-party estimates from this snapshot.</p>
                </div>
                <form method="GET" action="{{ route('admin.websites.show', $website) }}" class="grid gap-3 @md:grid-cols-3">
                    <input type="hidden" name="tab" value="seo">
                    <input type="hidden" name="seo_section" value="keywords">
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
        </div>
    @endif
    </div>
</div>
