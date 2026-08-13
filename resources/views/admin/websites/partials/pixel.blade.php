@php
    $pixelIsConnected = $website->pixel_last_seen_at?->greaterThan(now()->subDays(2)) === true;
    $pixelWasSeen = $website->pixel_last_seen_at !== null;
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
                <dd class="mt-1 text-xl font-semibold tabular-nums text-slate-950">{{ number_format($website->active_pixel_optimisations_count) }}</dd>
            </div>
            <div class="bg-white p-4">
                <dt class="text-sm text-slate-500">Pixel version</dt>
                <dd class="mt-1 font-mono text-sm font-semibold text-slate-950">{{ $website->pixel_version ?? '—' }}</dd>
            </div>
        </dl>
    </section>

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
