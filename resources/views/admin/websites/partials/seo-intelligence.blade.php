@php
    $availableSeoSections = $seoSnapshot ? ['overview', 'targets', 'actions', 'impact', 'pages', 'keywords', 'backlinks', 'competitors'] : ['overview', 'targets', 'actions', 'impact', 'pages', 'competitors'];
    $requestedSeoSection = request('seo_section', request()->has('seo_filter') ? 'keywords' : 'overview');
    $currentSeoSection = in_array($requestedSeoSection, $availableSeoSections, true) ? $requestedSeoSection : 'overview';
@endphp
<div id="website-panel-seo" class="space-y-6" role="region" aria-labelledby="website-tab-seo" data-tab-panel="seo" @if ($currentWebsiteSection !== 'seo') hidden @endif>
    <div class="space-y-6" data-seo-sections data-tabs-key="seo-intelligence" data-default-tab="{{ request('seo_section', request()->has('seo_filter') ? 'keywords' : 'overview') }}">
        <div class="min-w-0" role="navigation" aria-label="SEO Intelligence sections">
            <div class="ui-tabs">
                <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'overview']) }}" id="seo-section-tab-overview" class="ui-tab" @if ($currentSeoSection === 'overview') aria-current="page" @endif data-tab="overview">Overview</a>
                <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'targets']) }}" id="seo-section-tab-targets" class="ui-tab" @if ($currentSeoSection === 'targets') aria-current="page" @endif data-tab="targets">Target keywords</a>
                    <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions']) }}" id="seo-section-tab-actions" class="ui-tab" @if ($currentSeoSection === 'actions') aria-current="page" @endif data-tab="actions">Action list</a>
                <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact']) }}" id="seo-section-tab-impact" class="ui-tab" @if ($currentSeoSection === 'impact') aria-current="page" @endif data-tab="impact">SEO impact</a>
                @if ($seoSnapshot)
                    <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'keywords']) }}" id="seo-section-tab-keywords" class="ui-tab" @if ($currentSeoSection === 'keywords') aria-current="page" @endif data-tab="keywords">Keywords</a>
                    <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'backlinks']) }}" id="seo-section-tab-backlinks" class="ui-tab" @if ($currentSeoSection === 'backlinks') aria-current="page" @endif data-tab="backlinks">Backlinks</a>
                @endif
                    <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'competitors']) }}" id="seo-section-tab-competitors" class="ui-tab" @if ($currentSeoSection === 'competitors') aria-current="page" @endif data-tab="competitors">Competitors</a>
                <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'pages']) }}" id="seo-section-tab-pages" class="ui-tab" @if ($currentSeoSection === 'pages') aria-current="page" @endif>Pages</a>
            </div>
        </div>
        <div id="seo-section-panel-pages" role="region" aria-labelledby="seo-section-tab-pages" @if ($currentSeoSection !== 'pages') hidden @endif>
            @if ($currentSeoSection === 'pages') @include('admin.websites.partials.page-workspace') @endif
        </div>

        <div id="seo-section-panel-impact" role="region" aria-labelledby="seo-section-tab-impact" data-tab-panel="impact" @if ($currentSeoSection !== 'impact') hidden @endif>
            @if ($currentSeoSection === 'impact')
                @if ($seoImpact)
                    @include('admin.websites.seo-impact')
                @else
                    @include('admin.websites.seo-impacts')
                @endif
            @endif
        </div>

        <div id="seo-section-panel-competitors" role="region" aria-labelledby="seo-section-tab-competitors" data-tab-panel="competitors" @if ($currentSeoSection !== 'competitors') hidden @endif>
            @include('admin.websites.partials.competitors')
        </div>

        <div id="seo-section-panel-targets" role="region" aria-labelledby="seo-section-tab-targets" data-tab-panel="targets" @if ($currentSeoSection !== 'targets') hidden @endif>
            @include('admin.websites.partials.seo-target-keywords')
        </div>

        <div id="seo-section-panel-overview" role="region" aria-labelledby="seo-section-tab-overview" data-tab-panel="overview" @if ($currentSeoSection !== 'overview') hidden @endif>
    <section class="ui-panel overflow-hidden" aria-labelledby="seo-intelligence-title">
        <div class="@container border-b border-slate-950/10 p-4">
        <div class="flex flex-col gap-4 @xl:flex-row @xl:items-start @xl:justify-between">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <p class="font-medium uppercase tracking-widest text-slate-500 text-base sm:text-sm">Third-party market intelligence</p>
                    <p class="rounded-full bg-teal-50 px-2 py-1 font-medium text-teal-800 ring-1 ring-teal-700/10 text-base sm:text-sm">Estimated data</p>
                </div>
                <h2 id="seo-intelligence-title" class="mt-2 text-lg font-semibold text-slate-950">SEO Intelligence</h2>
                <p class="mt-1 max-w-3xl text-slate-600 text-base sm:text-sm">Organic visibility estimates for {{ $website->primaryDomain()?->domain ?: 'this website' }}. These figures are separate from Google Search Console performance.</p>
            </div>

            @if ($canManageWebsite)
                <div class="flex shrink-0 flex-col items-end gap-2">
                    @if ($dataForSeoConfigured)
                        <form method="POST" action="{{ route('admin.seo-intelligence.store', $website) }}">
                            @csrf
                            <button type="submit" @disabled($seoGeneration && in_array($seoGeneration->status, ['pending', 'processing'], true)) class="ui-button ui-button-secondary disabled:cursor-not-allowed">
                                {{ $seoSnapshot ? 'Refresh intelligence' : 'Generate intelligence' }}
                            </button>
                        </form>
                    @else
                        <p class="max-w-xs text-amber-700 text-base sm:text-sm">Add the SEO data provider credentials to Sitewell before generating intelligence.</p>
                    @endif
                    <form method="POST" action="{{ route('admin.seo-snapshot-settings.update', $website) }}" class="flex items-center gap-2">
                        @csrf @method('PUT')
                        <input type="hidden" name="seo_weekly_snapshots_enabled" value="0">
                        <input id="seo-weekly-snapshots" type="checkbox" name="seo_weekly_snapshots_enabled" value="1" @checked($website->seo_weekly_snapshots_enabled)>
                        <label for="seo-weekly-snapshots" class="ui-label">Automatic weekly snapshots</label>
                        <button type="submit" class="ui-button ui-button-secondary ui-button-small">Save</button>
                    </form>
                    <p class="max-w-sm text-right text-slate-500 text-base sm:text-sm">Enabling this also imports available monthly history once.</p>
                    <form method="POST" action="{{ route('admin.weekly-ranking-report-settings.update', $website) }}" class="ui-panel mt-2 grid max-w-sm gap-2 p-3 text-left">
                        @csrf @method('PUT')
                        <label for="weekly-ranking-reports" class="ui-label flex items-start gap-2">
                            <input type="hidden" name="weekly_ranking_reports_enabled" value="0">
                            <input id="weekly-ranking-reports" type="checkbox" name="weekly_ranking_reports_enabled" value="1" class="mt-1" @checked($website->weekly_ranking_reports_enabled)>
                            <span>
                                <span class="block text-sm font-medium text-slate-700">Weekly ranking email</span>
                                <span class="block text-xs leading-5 text-slate-500">Includes target keywords, Search Console performance and SEO estimates when available.</span>
                            </span>
                        </label>
                        <p class="leading-5 text-slate-500 text-base sm:text-sm">Email delivery does not run paid checks. Enable automatic weekly snapshots for fresh weekly target positions.</p>
                        <button type="submit" class="ui-button ui-button-secondary ui-button-small justify-self-start">Save email preference</button>
                    </form>
                </div>
            @endif
        </div>
        </div>

        @if ($seoGeneration && in_array($seoGeneration->status, ['pending', 'processing'], true))
            <div class="border-b border-blue-200 bg-blue-50 px-4 py-3">
                <p class="font-medium text-blue-900 text-base sm:text-sm">SEO intelligence is {{ $seoGeneration->status === 'pending' ? 'queued' : 'being generated' }}.</p>
                <p class="mt-1 text-blue-700 text-base sm:text-sm">This page remains available while the background job runs. Refresh shortly to see the results.</p>
            </div>
        @elseif ($seoGeneration?->status === 'failed')
            <div class="border-b border-red-200 bg-red-50 px-4 py-3">
                <p class="font-medium text-red-900 text-base sm:text-sm">The latest SEO intelligence refresh failed.</p>
                <p class="mt-1 text-red-700 text-base sm:text-sm">Existing successful data is retained. You can try again when the provider is available.</p>
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

            <div class="@container border-t border-slate-950/10">
            <div class="grid gap-px bg-slate-200 @3xl:grid-cols-[2fr_1fr]">
                <div class="bg-white p-4">
                    <p class="font-medium uppercase tracking-widest text-slate-500 text-base sm:text-sm">What can we improve?</p>
                    <h3 class="mt-2 text-base font-semibold text-slate-950">{{ number_format($strikingDistanceCount) }} striking-distance keywords</h3>
                    <p class="mt-1 text-slate-600 text-base sm:text-sm">Keywords currently ranking in positions 4–20 may offer the clearest near-term optimisation opportunities.</p>
                    <a href="{{ route('admin.websites.show', [$website, 'tab' => 'seo', 'seo_section' => 'keywords', 'seo_filter' => 'positions_11_20', 'seo_sort' => 'search_volume', 'seo_direction' => 'desc']) }}" class="mt-3 inline-flex text-sm font-medium text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">Review page-two keywords</a>
                </div>
                <div class="bg-slate-50 p-4">
                    <p class="font-medium text-slate-900 text-base sm:text-sm">Snapshot details</p>
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
            <div data-seo-history-charts class="grid gap-4 border-t border-slate-950/10 p-4">
                <x-progress-chart title="Estimated organic traffic" description="Monthly third-party organic visibility estimate." :points="$seoHistory" value-key="estimated_organic_traffic" format="traffic" />
                <x-progress-chart title="Ranking keywords" description="Keywords estimated to rank in Google's top 100." :points="$seoHistory" value-key="organic_keywords" />
            </div>
        @else
            <div class="p-8 text-center">
                <h3 class="text-base font-semibold text-slate-950">No SEO snapshot yet</h3>
                <p class="mx-auto mt-2 max-w-lg text-slate-600 text-base sm:text-sm">Generate the first snapshot to discover estimated organic rankings and search opportunities. The website does not need to be online.</p>
            </div>
        @endif
    </section>
        </div>

        <div id="seo-section-panel-actions" role="region" aria-labelledby="seo-section-tab-actions" data-tab-panel="actions" @if ($currentSeoSection !== 'actions') hidden @endif>
            @if ($currentSeoSection === 'actions') @include('admin.websites.partials.action-center') @endif
        </div>

    @if ($seoSnapshot)
        <div id="seo-section-panel-backlinks" role="region" aria-labelledby="seo-section-tab-backlinks" data-tab-panel="backlinks" @if ($currentSeoSection !== 'backlinks') hidden @endif>
        <section class="ui-panel" aria-labelledby="backlinks-title">
            <div class="border-b border-slate-950/10 p-4">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
                    <div><h3 id="backlinks-title" class="text-balance text-base font-semibold text-slate-950">Backlinks</h3><p class="mt-1 text-pretty text-base text-slate-600 sm:text-sm">Who links to this website, based on locally stored third-party estimates.</p></div>
                    @if ($latestBacklinkAudit)
                        <a href="{{ route('admin.backlink-audits.show', [$website, $latestBacklinkAudit]) }}" class="ui-button ui-button-secondary">View latest audit</a>
                    @endif
                </div>
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

            <div class="border-t border-slate-950/10 bg-slate-50 p-4">
                <form method="POST" action="{{ route('admin.backlink-audits.store', $website) }}" class="space-y-4">
                    @csrf
                    <div><h4 class="font-semibold text-slate-950">Actionable backlink audit</h4><p class="mt-1 text-slate-600 text-base sm:text-sm">Collect detailed links, linked pages, new and lost trends, and optional gaps against up to three competitors. This is a paid, explicit DataForSEO audit; results are reused for seven days.</p></div>
                    @if ($latestBacklinkAudit)<p class="text-slate-600 text-base sm:text-sm">Latest audit: {{ ucfirst(str_replace('_', ' ', $latestBacklinkAudit->status)) }} · {{ ($latestBacklinkAudit->completed_at ?? $latestBacklinkAudit->updated_at)->format('j M Y, H:i') }}</p>@endif
                    @if ($canManageWebsite)
                        <fieldset><legend class="text-sm font-medium text-slate-800">Compare competitors (optional)</legend><div class="mt-2 grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                            @forelse ($trackedCompetitors->where('excluded', false) as $competitor)
                                <label class="ui-label flex min-h-11 items-center gap-2 rounded-md border bg-white px-3 py-2"><input type="checkbox" name="competitor_ids[]" value="{{ $competitor->id }}"> <span class="break-all">{{ $competitor->domain }}</span></label>
                            @empty
                                <p class="text-slate-500 text-base sm:text-sm">Add competitors in the Competitors section to include link gaps.</p>
                            @endforelse
                        </div></fieldset>
                        <button type="submit" class="ui-button ui-button-primary">{{ $latestBacklinkAudit?->status === 'failed' ? 'Retry backlink audit' : 'Run backlink audit' }}</button>
                    @else
                        <p class="text-slate-500 text-base sm:text-sm">Managers can run backlink audits. Viewers can inspect saved results.</p>
                    @endif
                </form>
            </div>

            <div class="border-t border-slate-950/10 p-4">
                <div class="flex flex-col gap-1 sm:flex-row sm:items-end sm:justify-between">
                    <div>
                        <h4 class="text-balance font-semibold text-slate-950">Strongest referring domains</h4>
                        <p class="text-pretty text-base text-slate-600 sm:text-sm">The highest-ranked domains in the stored sample.</p>
                    </div>
                    <p class="text-base tabular-nums text-slate-500 sm:text-sm">{{ number_format($seoSnapshot->referringDomains()->count()) }} domains stored from this sample</p>
                </div>

                <form method="GET" action="{{ route('admin.websites.section', [$website, 'seo']) }}" class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-[minmax(0,1fr)_auto_auto_auto_auto]">
                    <input type="hidden" name="tab" value="seo"><input type="hidden" name="seo_section" value="backlinks">
                    <label><span class="sr-only">Search referring domains</span><input name="backlink_search" value="{{ $backlinkSearch }}" placeholder="Search domains" class="ui-input min-h-11 w-full"></label>
                    <label><span class="sr-only">Filter referring domains by rank</span><select name="backlink_min_rank" class="ui-input min-h-11 w-full"><option value="0" @selected($backlinkMinRank === 0)>All domain ranks</option><option value="40" @selected($backlinkMinRank === 40)>Rank 40+</option><option value="70" @selected($backlinkMinRank === 70)>Rank 70+</option></select></label>
                    <label><span class="sr-only">Sort referring domains</span><select name="backlink_sort" class="ui-input min-h-11 w-full"><option value="domain_rank" @selected($backlinkSort === 'domain_rank')>Domain rank</option><option value="backlinks_count" @selected($backlinkSort === 'backlinks_count')>Backlinks</option><option value="last_seen" @selected($backlinkSort === 'last_seen')>Last seen</option><option value="domain" @selected($backlinkSort === 'domain')>Domain</option></select></label>
                    <label><span class="sr-only">Sort direction</span><select name="backlink_direction" class="ui-input min-h-11 w-full"><option value="desc" @selected($backlinkDirection === 'desc')>Descending</option><option value="asc" @selected($backlinkDirection === 'asc')>Ascending</option></select></label>
                    <button type="submit" class="ui-button ui-button-secondary">Apply</button>
                </form>

                <div class="-mx-4 -my-2 mt-4 overflow-x-auto whitespace-nowrap">
                    <div class="inline-block min-w-full px-4 py-2 align-middle">
                        <table class="w-full divide-y divide-slate-950/10">
                            <thead>
                                <tr>
                                    <th class="whitespace-nowrap py-3 pr-4 text-left">Domain</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Domain rank</th>
                                    <th class="whitespace-nowrap px-4 py-3 text-right">Backlinks</th><th class="whitespace-nowrap py-3 pl-4 text-right">Last seen</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-950/5">
                                @forelse ($seoReferringDomains as $referringDomain)
                                    <tr>
                                        <td class="py-3 pr-4 font-medium text-slate-950">{{ $referringDomain->domain }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ $referringDomain->domain_rank ?? '—' }}</td>
                                        <td class="px-4 py-3 text-right tabular-nums text-slate-700">{{ number_format($referringDomain->backlinks_count) }}</td><td class="py-3 pl-4 text-right text-slate-600">{{ $referringDomain->last_seen?->format('j M Y') ?? '—' }}</td>
                                    </tr>
                                @empty
                                    <tr><td colspan="4" class="py-8 text-center text-slate-500">No referring domains matched this stored snapshot.</td></tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
                @if (method_exists($seoReferringDomains, 'links'))<div class="mt-4">{{ $seoReferringDomains->links() }}</div>@endif
            </div>
        </section>
        </div>

        <div id="seo-section-panel-keywords" role="region" aria-labelledby="seo-section-tab-keywords" data-tab-panel="keywords" @if ($currentSeoSection !== 'keywords') hidden @endif>
        <section class="ui-panel" aria-labelledby="ranking-keywords-title">
            <div class="@container border-b border-slate-950/10 p-4">
            <div class="flex flex-col gap-4 @4xl:flex-row @4xl:items-end @4xl:justify-between">
                <div>
                    <h3 id="ranking-keywords-title" class="text-base font-semibold text-slate-950">Ranking keywords</h3>
                    <p class="mt-1 text-slate-600 text-base sm:text-sm">Locally stored third-party estimates from this snapshot.</p>
                </div>
                <form method="GET" action="{{ route('admin.websites.section', [$website, 'seo']) }}" class="grid gap-3 @md:grid-cols-3">
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
                        <button type="submit" class="ui-button ui-button-secondary">Apply</button>
                    </div>
                </form>
            </div>
            </div>

            <div class="overflow-x-auto">
                <table class="min-w-full divide-y divide-slate-950/10">
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
                                    <p class="truncate font-medium" title="{{ $keyword->keyword }}"><a href="{{ route('admin.seo-keywords.show', [$website, $keyword]) }}" class="text-teal-700 underline decoration-teal-700/30 underline-offset-4 hover:decoration-teal-700">{{ $keyword->keyword }}</a></p>
                                    @if ($keyword->ranking_url)
                                        <p class="mt-1 truncate text-slate-500 text-base sm:text-sm" title="{{ $keyword->ranking_url }}"><a href="{{ $keyword->ranking_url }}" target="_blank" rel="noopener noreferrer" class="hover:text-teal-700">{{ \Illuminate\Support\Str::after($keyword->ranking_url, '://') }}</a></p>
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
                <div class="border-t border-slate-950/10 p-4">{{ $seoKeywords->links() }}</div>
            @endif
        </section>
        </div>
    @endif
    </div>
</div>
