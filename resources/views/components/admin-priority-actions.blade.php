@props(['actions', 'counts', 'state', 'siteFilter' => null])
<div class="space-y-5">
    <header>
        <h2 class="text-xl font-semibold text-balance text-slate-950">Your prioritised action list</h2>
        <p class="mt-2 text-base text-pretty text-slate-600 sm:text-sm">The same recommendations as each website’s action list, ranked together. Related findings stay grouped by page.</p>
    </header>
    <nav class="ui-tabs" aria-label="Priority status">
        @foreach (['open' => 'To do', 'queued' => 'Queued', 'measuring' => 'Measuring', 'review' => 'Results to review', 'completed' => 'Completed'] as $key => $label)
            <a href="{{ route('admin.overview', array_filter(['hub' => 'priorities', 'site_id' => $siteFilter, 'action_state' => $key])) }}" class="ui-tab" @if ($state === $key) aria-current="page" @endif>{{ $label }} · {{ $counts->get($key, 0) }}</a>
        @endforeach
    </nav>
    <div class="ui-panel ui-section @container">
        <div class="divide-y divide-slate-950/10">
            @forelse ($actions as $action)
                <article class="grid gap-4 py-5 first:pt-0 last:pb-0 @3xl:grid-cols-[minmax(0,1fr)_auto] @3xl:items-start">
                    <div class="min-w-0">
                        <p class="flex flex-wrap items-center gap-2 text-base sm:text-sm">
                            <a href="{{ route('admin.websites.section', [$action['website'], 'seo', 'seo_section' => 'actions']) }}" class="font-medium text-teal-700 hover:underline">{{ $action['website']->name }}</a>
                            <span class="text-slate-400" aria-hidden="true">·</span>
                            <span class="tabular-nums text-slate-500">Priority {{ $action['score'] }}</span>
                            @if (! $action['website']->is_active)<span class="rounded-full bg-amber-50 px-2 py-1 text-amber-800">Website paused</span>@endif
                        </p>
                        <h3 class="mt-2 break-words font-semibold text-balance text-slate-950">{{ $action['title'] }}</h3>
                        <p class="mt-2 line-clamp-3 text-base text-pretty text-slate-600 sm:text-sm">{{ $action['reason'] }}</p>
                        @if (count($action['evidence']))
                            <details class="mt-3 text-base sm:text-sm">
                                <summary class="cursor-pointer font-medium text-slate-600 hover:text-teal-700">{{ count($action['evidence']) }} supporting observations</summary>
                                <ul role="list" class="mt-3 max-h-64 space-y-3 overflow-y-auto overscroll-contain">
                                    @foreach ($action['evidence'] as $item)
                                        <li><p class="font-medium text-slate-700">{{ $item['title'] }}</p><p class="mt-1 text-slate-600">{{ $item['reason'] }}</p><p class="mt-1 text-slate-500">{{ ['audit' => 'Site audit', 'search' => 'Search Console', 'seo' => 'SEO estimates', 'competitor' => 'Competitor research', 'backlink' => 'Backlink research'][$item['source']] ?? $item['source'] }} · {{ $item['date']->format('j M Y') }}</p></li>
                                    @endforeach
                                </ul>
                            </details>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-2 @3xl:justify-end">
                        @if ($action['impact'])
                            <a href="{{ route('admin.websites.section', [$action['website'], 'seo', 'seo_section' => 'impact', 'seo_impact' => $action['impact']->id]) }}" class="ui-button ui-button-secondary">{{ $action['stage'] === 'review' ? 'Review result' : 'View progress' }}</a>
                        @elseif ($action['stage'] === 'open')
                            <form method="POST" action="{{ route('admin.website-actions.queue', $action['website']) }}">
                                @csrf
                                <input type="hidden" name="action_key" value="{{ $action['key'] }}">
                                <input type="hidden" name="return_to" value="overview">
                                <input type="hidden" name="overview_site_id" value="{{ $siteFilter }}">
                                <button type="submit" class="ui-button ui-button-secondary" aria-label="Add {{ $action['title'] }} for {{ $action['website']->name }} to content queue">Add to content queue</button>
                            </form>
                        @endif
                        @if ($action['url'])
                            <a href="{{ route('admin.websites.section', [$action['website'], 'seo', 'seo_section' => 'pages', 'page_url' => $action['url']]) }}" class="ui-button ui-button-secondary">View page</a>
                        @endif
                    </div>
                </article>
            @empty
                <div class="py-4">
                    <h3 class="font-semibold">{{ $state === 'open' ? 'No outstanding priorities' : 'No work in this stage' }}</h3>
                    <p class="mt-2 text-base text-slate-600 sm:text-sm">{{ $state === 'open' ? 'New audit and search findings will appear here as Sitewell checks your websites.' : 'Work moves through these stages as it is queued, published and measured.' }}</p>
                </div>
            @endforelse
        </div>
    </div>
    {{ $actions->links() }}
</div>
