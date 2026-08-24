@php
    $pixelIsConnected = $website->pixel_last_seen_at?->greaterThan(now()->subDays(2)) === true;
    $pixelWasSeen = $website->pixel_last_seen_at !== null;
    $livePixelOptimisations = $pixelOptimisations->where('status', \App\Enums\OptimisationStatus::Deployed)->groupBy('url');
    $reviewablePixelOptimisations = $pixelOptimisations->whereIn('status', [\App\Enums\OptimisationStatus::Draft, \App\Enums\OptimisationStatus::Approved]);
    $pixelHistory = $pixelOptimisations->whereIn('status', [\App\Enums\OptimisationStatus::RolledBack, \App\Enums\OptimisationStatus::Failed])->take(20);
    $pendingPixelContentRequests = $website->contentRequests->whereNull('picked_up_at')->whereNull('pixel_processed_at');
@endphp

<div id="website-panel-pixel" class="space-y-6" role="tabpanel" aria-labelledby="website-tab-pixel" data-tab-panel="pixel" hidden>
    <section class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="pixel-title">
        <div class="flex flex-col gap-4 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-widest text-teal-700">Deployment connection</p>
                <h2 id="pixel-title" class="mt-1 text-xl font-semibold text-slate-950">Sitewell Pixel</h2>
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Deploy approved SEO and content optimisations without changing the website's source code.</p>
            </div>
            <div @class([
                'inline-flex items-center gap-2 self-start rounded-full px-3 py-1.5 text-sm font-medium',
                'bg-emerald-50 text-emerald-700' => $website->pixel_enabled && $pixelIsConnected,
                'bg-amber-50 text-amber-800' => $website->pixel_enabled && $pixelWasSeen && ! $pixelIsConnected,
                'bg-slate-100 text-slate-600' => ! $website->pixel_enabled || ! $pixelWasSeen,
            ])>
                <span @class([
                    'size-2 rounded-full',
                    'bg-emerald-500' => $website->pixel_enabled && $pixelIsConnected,
                    'bg-amber-500' => $website->pixel_enabled && $pixelWasSeen && ! $pixelIsConnected,
                    'bg-slate-400' => ! $website->pixel_enabled || ! $pixelWasSeen,
                ]) aria-hidden="true"></span>
                {{ ! $website->pixel_enabled ? 'Disabled' : ($pixelIsConnected ? 'Connected' : ($pixelWasSeen ? 'Not seen recently' : 'Not detected')) }}
            </div>
        </div>

        <dl class="grid grid-cols-2 gap-px bg-slate-200 lg:grid-cols-4">
            <div class="bg-white p-4">
                <dt class="text-sm text-slate-500">Last seen</dt>
                <dd class="mt-1 font-semibold text-slate-950">{{ $website->pixel_last_seen_at?->diffForHumans() ?? 'Never' }}</dd>
            </div>
            <div class="bg-white p-4">
                <dt class="text-sm text-slate-500">Pages detected</dt>
                <dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ number_format($website->pixel_pages_count) }}</dd>
            </div>
            <div class="bg-white p-4">
                <dt class="text-sm text-slate-500">Active optimisations</dt>
                <dd class="mt-1"><a href="#pixel-live-changes" class="text-xl font-semibold tabular-nums text-slate-950 underline decoration-slate-300 underline-offset-4 hover:decoration-slate-900">{{ number_format($website->active_pixel_optimisations_count) }}</a></dd>
            </div>
            <div class="bg-white p-4">
                <dt class="text-sm text-slate-500">Pixel version</dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-slate-950">{{ $website->pixel_version ?? '—' }}</dd>
            </div>
        </dl>
    </section>

    <section id="pixel-live-changes" class="overflow-hidden rounded-lg border border-slate-200 bg-white shadow-sm" aria-labelledby="pixel-live-title">
        <div class="flex flex-col gap-3 border-b border-slate-200 p-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-widest text-emerald-700">Currently deployed</p>
                <h2 id="pixel-live-title" class="mt-1 text-lg font-semibold text-slate-950">Live Pixel changes</h2>
                <p class="mt-1 text-sm text-slate-600">These changes are included in public Pixel payloads. They remain client-side and depend on the Pixel loading on the matching page.</p>
            </div>
            <span class="self-start rounded-full bg-emerald-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-emerald-800">{{ $livePixelOptimisations->flatten()->count() }} live</span>
        </div>

        <div class="divide-y divide-slate-200">
            @forelse ($livePixelOptimisations as $url => $optimisations)
                <article class="p-4">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <p class="truncate font-mono text-xs text-slate-500" title="{{ $url }}">{{ $url }}</p>
                            <p class="mt-1 text-sm font-semibold text-slate-950">{{ $optimisations->count() }} live {{ Str::plural('change', $optimisations->count()) }}</p>
                        </div>
                        <a href="{{ $url }}" target="_blank" rel="noreferrer" class="shrink-0 rounded-md border border-slate-200 px-3 py-2 text-xs font-medium text-slate-700 hover:bg-slate-50">Open live page</a>
                    </div>
                    <div class="mt-3 grid gap-3 lg:grid-cols-2">
                        @foreach ($optimisations as $optimisation)
                            @php($latestDeployment = $optimisation->deployments->first())
                            <div class="rounded-lg border border-emerald-200 bg-emerald-50/50 p-3">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <span class="text-sm font-semibold capitalize text-emerald-950">{{ str_replace('_', ' ', $optimisation->type->value) }}</span>
                                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Live via Pixel</span>
                                </div>
                                @if ($optimisation->selector)
                                    <p class="mt-1 font-mono text-xs text-emerald-800">{{ $optimisation->selector }}{{ $optimisation->attribute ? ' · '.$optimisation->attribute : '' }}</p>
                                @endif
                                <dl class="mt-3 grid gap-2 text-sm">
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Original</dt><dd class="mt-1 whitespace-pre-wrap text-slate-700">{{ $optimisation->currentVersion?->original_value ?? 'Not recorded' }}</dd></div>
                                    <div><dt class="text-xs font-medium uppercase tracking-wide text-slate-500">Live value</dt><dd class="mt-1 whitespace-pre-wrap font-medium text-slate-950">{{ $optimisation->currentVersion?->new_value }}</dd></div>
                                </dl>
                                <div class="mt-3 flex flex-wrap items-center justify-between gap-2 border-t border-emerald-200 pt-3">
                                    <p class="text-xs text-slate-500">Deployed {{ $latestDeployment?->performed_at?->diffForHumans() ?? $optimisation->deployed_at?->diffForHumans() }}{{ $latestDeployment?->performer ? ' by '.$latestDeployment->performer->name : '' }}</p>
                                    @if ($optimisation->page?->report)
                                        <div class="flex gap-2">
                                            <a href="{{ route('admin.website-health-report-pages.show', [$website, $optimisation->page->report, $optimisation->page]) }}" class="rounded-md border border-slate-200 bg-white px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Details</a>
                                            <form method="POST" action="{{ route('admin.optimisations.rollback', [$website, $optimisation->page->report, $optimisation->page, $optimisation]) }}">@csrf<button class="rounded-md border border-red-200 bg-white px-2.5 py-1.5 text-xs font-medium text-red-700 hover:bg-red-50">Rollback</button></form>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        @endforeach
                    </div>
                </article>
            @empty
                <p class="p-6 text-center text-sm text-slate-500">No Pixel changes are currently deployed.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="pixel-review-title">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-widest text-amber-700">Approval queue</p>
                <h2 id="pixel-review-title" class="mt-1 text-lg font-semibold text-slate-950">Pixel drafts awaiting review</h2>
                <p class="mt-1 text-sm text-slate-600">Generated report fixes and eligible content todos appear here before anything becomes live.</p>
            </div>
            <span class="self-start rounded-full bg-amber-100 px-2.5 py-1 text-xs font-semibold tabular-nums text-amber-800">{{ $reviewablePixelOptimisations->count() }} waiting</span>
        </div>

        <div class="mt-4 space-y-3">
            @forelse ($reviewablePixelOptimisations as $optimisation)
                <article class="rounded-lg border border-slate-200 p-3">
                    <div class="flex flex-col gap-3 sm:flex-row sm:items-start sm:justify-between">
                        <div class="min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="text-sm font-semibold capitalize text-slate-950">{{ str_replace('_', ' ', $optimisation->type->value) }}</span>
                                @if ($optimisation->contentRequest)
                                    <span class="rounded-full bg-violet-100 px-2 py-0.5 text-xs font-medium text-violet-800">Content todo</span>
                                @else
                                    <span class="rounded-full bg-blue-100 px-2 py-0.5 text-xs font-medium text-blue-800">Health report</span>
                                @endif
                            </div>
                            <p class="mt-1 truncate font-mono text-xs text-slate-500" title="{{ $optimisation->url }}">{{ $optimisation->url }}</p>
                            @if ($optimisation->target_description)<p class="mt-1 text-xs text-slate-500">{{ $optimisation->target_description }}</p>@endif
                            <p class="mt-2 whitespace-pre-wrap text-sm font-medium text-slate-900">{{ $optimisation->currentVersion?->new_value }}</p>
                        </div>
                        @if ($optimisation->page?->report)
                            <div class="flex shrink-0 flex-wrap gap-2">
                                <a href="{{ route('admin.website-health-report-pages.show', [$website, $optimisation->page->report, $optimisation->page]) }}" class="rounded-md border border-slate-200 px-2.5 py-1.5 text-xs font-medium text-slate-700 hover:bg-slate-50">Review details</a>
                                <form method="POST" action="{{ route('admin.optimisations.deploy', [$website, $optimisation->page->report, $optimisation->page, $optimisation]) }}">@csrf<button class="rounded-md bg-teal-700 px-2.5 py-1.5 text-xs font-medium text-white hover:bg-teal-800">Approve &amp; deploy</button></form>
                            </div>
                        @endif
                    </div>
                </article>
            @empty
                <p class="rounded-lg bg-slate-50 p-4 text-sm text-slate-500">No Pixel drafts are waiting for review.</p>
            @endforelse
        </div>
    </section>

    <section class="rounded-lg border border-violet-200 bg-violet-50 p-4 shadow-sm" aria-labelledby="pixel-content-title">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div>
                <p class="text-xs font-medium uppercase tracking-widest text-violet-700">Content queue</p>
                <h2 id="pixel-content-title" class="mt-1 text-lg font-semibold text-violet-950">Prepare pending todos for Pixel</h2>
                <p class="mt-1 max-w-3xl text-sm text-violet-800">Sitewell checks pending todos against recently detected pages and prepares safe title or meta-description drafts where Pixel is suitable. New pages and substantial body content stay in the GitHub queue.</p>
            </div>
            <form method="POST" action="{{ route('admin.websites.pixel.content-requests.store', $website) }}" class="shrink-0">
                @csrf
                <button type="submit" @disabled(! $website->pixel_enabled || $pendingPixelContentRequests->isEmpty()) class="rounded-md bg-violet-700 px-3 py-2 text-sm font-medium text-white hover:bg-violet-800 disabled:cursor-not-allowed disabled:bg-violet-300">Prepare {{ $pendingPixelContentRequests->count() }} {{ Str::plural('todo', $pendingPixelContentRequests->count()) }}</button>
            </form>
        </div>
        @if ($website->contentRequests->whereNotNull('pixel_processed_at')->isNotEmpty())
            <div class="mt-4 space-y-2 border-t border-violet-200 pt-4">
                @foreach ($website->contentRequests->whereNotNull('pixel_processed_at')->take(10) as $contentRequest)
                    @php($todoOptimisations = $pixelOptimisations->where('content_request_id', $contentRequest->id))
                    <div class="flex flex-col gap-2 rounded-lg bg-white/70 p-3 text-sm sm:flex-row sm:items-start sm:justify-between">
                        <p class="line-clamp-2 text-slate-700">{{ $contentRequest->instructions }}</p>
                        <span class="shrink-0 rounded-full px-2.5 py-1 text-xs font-medium {{ $todoOptimisations->isNotEmpty() ? 'bg-emerald-100 text-emerald-800' : 'bg-slate-100 text-slate-600' }}">{{ $todoOptimisations->isNotEmpty() ? $todoOptimisations->count().' draft '.Str::plural('change', $todoOptimisations->count()) : 'GitHub needed' }}</span>
                    </div>
                @endforeach
            </div>
        @endif
    </section>

    @if ($pixelHistory->isNotEmpty())
        <details class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm">
            <summary class="cursor-pointer text-sm font-semibold text-slate-900">Recent Pixel deployment history</summary>
            <div class="mt-4 overflow-x-auto">
                <table class="min-w-full text-sm"><thead><tr class="border-b text-left text-xs uppercase text-slate-500"><th class="py-2 pr-3">Page</th><th class="py-2 pr-3">Change</th><th class="py-2 pr-3">Status</th><th class="py-2">Updated</th></tr></thead><tbody>@foreach ($pixelHistory as $optimisation)<tr class="border-b border-slate-100"><td class="max-w-80 truncate py-2 pr-3 font-mono text-xs" title="{{ $optimisation->url }}">{{ $optimisation->url }}</td><td class="py-2 pr-3 capitalize">{{ str_replace('_', ' ', $optimisation->type->value) }}</td><td class="py-2 pr-3 capitalize">{{ str_replace('_', ' ', $optimisation->status->value) }}</td><td class="py-2 text-slate-500">{{ $optimisation->updated_at->diffForHumans() }}</td></tr>@endforeach</tbody></table>
            </div>
        </details>
    @endif

    <section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="pixel-installation-title">
        <div class="max-w-3xl">
            <p class="text-xs font-medium uppercase tracking-widest text-slate-500">Installation</p>
            <h2 id="pixel-installation-title" class="mt-1 text-lg font-semibold text-slate-950">Add the Pixel once</h2>
            <p class="mt-1 text-sm text-slate-600">Add this code before the closing <code class="font-mono text-xs text-slate-800">&lt;/head&gt;</code> tag on every page of the website. Keep the <code class="font-mono text-xs text-slate-800">async</code> attribute so it never blocks the page.</p>
        </div>

        <div class="mt-4 overflow-hidden rounded-lg bg-slate-950">
            <div class="flex items-center justify-between gap-3 border-b border-white/10 px-3 py-2">
                <span class="font-mono text-xs text-slate-400">HTML</span>
                <button type="button" class="js-copy-text rounded-md border border-white/15 bg-white/10 px-3 py-1.5 text-sm font-medium text-white hover:bg-white/15" data-copy-target="pixel-installation-snippet" data-copy-label="Copy snippet" data-copied-label="Copied">Copy snippet</button>
            </div>
            <textarea id="pixel-installation-snippet" class="h-40 w-full resize-y border-0 bg-slate-950 p-4 font-mono text-sm leading-6 text-slate-100 focus:outline-2 focus:outline-offset-[-2px] focus:outline-teal-400" readonly spellcheck="false">{{ $pixelInstallationSnippet }}</textarea>
        </div>

        <div class="mt-5 grid gap-4 lg:grid-cols-2">
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-semibold text-slate-950">How connection detection works</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">After the Pixel receives a valid payload, it sends a small heartbeat at most once per browser and page each day. Sitewell stores the last-seen time and unique normalized pages—not page-view analytics.</p>
                @if ($website->pixel_last_seen_url)
                    <p class="mt-3 truncate text-xs text-slate-500" title="{{ $website->pixel_last_seen_url }}">Last URL: {{ $website->pixel_last_seen_url }}</p>
                @endif
            </div>
            <div class="rounded-lg border border-slate-200 p-4">
                <h3 class="text-sm font-semibold text-slate-950">Content Security Policy</h3>
                <p class="mt-2 text-sm leading-6 text-slate-600">Restrictive websites must allow the configured Pixel host in <code class="font-mono text-xs">script-src</code> and the API host in <code class="font-mono text-xs">connect-src</code>. Do not weaken other CSP directives.</p>
            </div>
        </div>

        @if ($canManageWebsite)
            <div class="flex flex-col gap-3 border-t border-slate-200 bg-slate-50 p-4 sm:flex-row sm:items-center sm:justify-between">
                <div><p class="text-sm font-medium text-slate-900">Site-wide controls</p><p class="mt-1 text-xs text-slate-500">Disabling Pixel immediately removes every Pixel change from public payloads without deleting deployment history.</p></div>
                <div class="flex shrink-0 flex-wrap gap-2">
                    <form method="POST" action="{{ route('admin.websites.pixel.update', $website) }}">
                        @csrf @method('PUT')
                        <input type="hidden" name="pixel_enabled" value="{{ $website->pixel_enabled ? '0' : '1' }}">
                        <button class="rounded-md border border-slate-300 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-100">{{ $website->pixel_enabled ? 'Disable all Pixel changes' : 'Enable Pixel' }}</button>
                    </form>
                    <form method="POST" action="{{ route('admin.websites.pixel.rotate-key', $website) }}" onsubmit="return confirm('Rotate the public Pixel key? The current installation will stop working until its snippet is replaced.')">
                        @csrf
                        <button class="rounded-md border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Rotate public key</button>
                    </form>
                </div>
            </div>
        @endif
    </section>
</div>
