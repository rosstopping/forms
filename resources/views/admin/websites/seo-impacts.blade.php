    <div class="space-y-6">
        <header>
            <h1 class="mt-4 text-2xl font-semibold tracking-tight text-slate-950">SEO impact</h1>
            <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Follow each change from its original brief to live delivery and measured results. Reviews compare the same pages and search terms over equal 28-day periods.</p>
        </header>
        <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
            Reviews needing a decision appear first. Within each stage, priority is business value × confidence ÷ effort. Use “Bump to top” to apply that priority to the content queue.
        </div>
        <div class="space-y-3">
            @forelse ($impacts as $impact)
                <article class="rounded-lg border border-slate-200 bg-white p-5">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2 text-xs">
                                <span class="rounded-full bg-slate-100 px-2.5 py-1 font-medium text-slate-700">{{ str($impact->status)->replace('_', ' ')->ucfirst() }}</span>
                                @if ($impact->outcome)<span class="rounded-full bg-teal-50 px-2.5 py-1 font-medium text-teal-800">{{ str($impact->outcome)->replace('_', ' ')->ucfirst() }}</span>@endif
                                <span class="text-slate-500">Priority {{ $impact->priorityScore() }}</span>
                            </div>
                            <h2 class="mt-3 font-semibold text-slate-950"><a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $impact->id]) }}" class="hover:underline">{{ $impact->title }}</a></h2>
                            <p class="mt-2 text-sm text-slate-600">{{ $impact->target_urls ? implode(', ', $impact->target_urls) : 'Add the canonical target pages to complete this brief.' }}</p>
                            @if ($impact->live_at)
                                <p class="mt-2 text-xs text-slate-500">Confirmed live {{ $impact->live_at->setTimezone('America/Los_Angeles')->format('j M Y') }} · {{ ucfirst($impact->primary_metric) }} is the primary measure</p>
                            @elseif ($impact->generation?->merged_at)
                                <p class="mt-2 text-xs text-amber-800">Pull request merged · Live confirmation still needed</p>
                            @endif
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <a href="{{ route('admin.websites.section', [$website, 'seo', 'seo_section' => 'impact', 'seo_impact' => $impact->id]) }}" class="rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">View impact</a>
                            @if ($website->isManageableBy(Auth::user()) && $impact->status === 'planned' && $impact->contentRequest && ! $impact->contentRequest->picked_up_at)
                                <form method="POST" action="{{ route('admin.content-requests.bump', [$website, $impact->contentRequest]) }}">@csrf<button class="rounded-md border border-slate-200 px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Bump to top</button></form>
                            @endif
                        </div>
                    </div>
                </article>
            @empty
                <div class="rounded-lg border border-dashed border-slate-300 p-8 text-center">
                    <h2 class="font-semibold text-slate-900">Start with your next recommendation</h2>
                    <p class="mt-2 text-sm text-slate-600">New queued recommendations and content requests automatically get an impact brief. Existing history is not labelled as measured work.</p>
                    <a href="{{ route('admin.websites.section', [$website, 'content']) }}" class="mt-4 inline-block text-sm font-medium text-slate-900 underline">Open the content queue</a>
                </div>
            @endforelse
        </div>
        {{ $impacts->links() }}
    </div>
