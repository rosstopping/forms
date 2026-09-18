@php
    $actionFilter = request('action_state', 'open');
    $actionFilter = in_array($actionFilter, ['open', 'queued', 'measuring', 'review', 'completed'], true) ? $actionFilter : 'open';
    $actionRows = $unifiedActions->where('stage', $actionFilter)->values();
    $actionPage = new \Illuminate\Pagination\LengthAwarePaginator($actionRows->forPage(max(1, request()->integer('actions_page', 1)), 20)->values(), $actionRows->count(), 20, max(1, request()->integer('actions_page', 1)), ['path' => request()->url(), 'pageName' => 'actions_page', 'query' => request()->except('actions_page')]);
@endphp
<section class="space-y-5" aria-labelledby="unified-actions-title">
    <header><h2 id="unified-actions-title" class="text-xl font-semibold">Your prioritised action list</h2><p class="mt-2 text-base text-slate-600 sm:text-sm">Audit findings, Search Console opportunities, SEO estimates, competitor research and backlink opportunities in one place. Related findings are combined by page; technical blockers come first. Adding work records its evidence and starts automatic impact tracking.</p></header>
    <nav class="ui-tabs" aria-label="Action status">
        @foreach (['open' => 'To do', 'queued' => 'Queued', 'measuring' => 'Measuring', 'review' => 'Results to review', 'completed' => 'Completed'] as $key => $label)
            <a class="ui-tab" href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'actions', 'action_state' => $key]) }}" @if ($key === $actionFilter) aria-current="page" @endif>{{ $label }} · {{ $unifiedActions->where('stage', $key)->count() }}</a>
        @endforeach
    </nav>
    <div class="divide-y divide-slate-950/10">
        @forelse ($actionPage as $action)
            <article class="space-y-3 py-5 first:pt-0">
                <div class="flex flex-wrap items-start justify-between gap-4">
                    <div class="min-w-0 flex-1"><p class="text-base font-medium text-teal-700 sm:text-sm">Priority {{ $action['score'] }} · {{ implode(' + ', array_map(fn ($source) => ['audit' => 'Site audit', 'search' => 'Search Console', 'seo' => 'SEO estimates', 'competitor' => 'Competitor research', 'backlink' => 'Backlink research'][$source] ?? $source, $action['sources'])) }}</p><h3 class="mt-2 font-semibold">{{ $action['title'] }}</h3><p class="mt-2 text-base text-slate-600 sm:text-sm">{{ $action['reason'] }}</p></div>
                    <div class="flex flex-wrap gap-2">
                        @if ($action['url'])<a class="ui-button ui-button-secondary" href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'pages', 'page_url' => $action['url']]) }}">View page</a>@endif
                        @if ($action['impact'])<a class="ui-button ui-button-secondary" href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $action['impact']->id]) }}">View progress</a>
                        @elseif ($action['stage'] === 'open' && $canManageWebsite)
                            <form method="POST" action="{{ route('admin.website-actions.queue', $website) }}">@csrf<input type="hidden" name="action_key" value="{{ $action['key'] }}"><button type="submit" class="ui-button ui-button-secondary">Add to content queue</button></form>
                        @endif
                    </div>
                </div>
                <details class="ui-well p-4"><summary class="cursor-pointer text-sm font-medium">{{ count($action['evidence']) }} supporting observations</summary><ul role="list" class="mt-3 space-y-3">@foreach ($action['evidence'] as $item)<li class="text-base text-slate-600 sm:text-sm"><strong class="font-medium text-slate-800">{{ $item['title'] }}</strong> — {{ $item['reason'] }}<p class="mt-1 text-sm text-slate-500">Recorded {{ $item['date']->format('j M Y') }}</p></li>@endforeach</ul></details>
            </article>
        @empty
            <div class="ui-well p-6"><h3 class="font-semibold">Nothing {{ $actionFilter === 'open' ? 'waiting to be actioned' : 'in this stage' }}</h3><p class="mt-2 text-base text-slate-600 sm:text-sm">This list uses saved evidence. Fresh audits and search checks will bring new opportunities here automatically.</p></div>
        @endforelse
    </div>
    {{ $actionPage->links() }}
</section>
