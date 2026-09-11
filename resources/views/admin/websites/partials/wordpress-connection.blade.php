@php
    $wordpressConnection = $website->wordpressConnection;
    $wordpressConnected = $wordpressConnection?->isConnected() === true;
    $pairingCode = session('wordpress_pairing_code');
    $latestRelease = $website->wordpressStaticReleases->first();
@endphp

<section class="rounded-lg border border-slate-200 bg-white p-4 shadow-sm" aria-labelledby="wordpress-connection-title">
    <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
            <div class="flex flex-wrap items-center gap-2">
                <p class="text-xs font-medium uppercase tracking-wide text-slate-500">Static delivery</p>
                @if ($wordpressConnected)
                    <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-800">Connected</span>
                @elseif ($wordpressConnection?->pairing_code_expires_at?->isFuture())
                    <span class="rounded-full bg-amber-100 px-2 py-0.5 text-xs font-medium text-amber-800">Waiting for WordPress</span>
                @else
                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Not connected</span>
                @endif
            </div>
            <h2 id="wordpress-connection-title" class="mt-1 font-semibold">WordPress frontend plugin</h2>

            @if ($wordpressConnected)
                <p class="mt-1 text-sm text-slate-600">
                    Connected to <span class="font-medium text-slate-900">{{ $wordpressConnection->wordpress_url }}</span>
                    @if ($wordpressConnection->plugin_version)
                        using plugin v{{ $wordpressConnection->plugin_version }}
                    @endif
                </p>
                <p class="mt-1 text-xs text-slate-500">Last checked in {{ $wordpressConnection->last_seen_at?->diffForHumans() ?: 'never' }}.@if ($wordpressConnection->last_deployed_at) Latest release went live {{ $wordpressConnection->last_deployed_at->diffForHumans() }}.@endif</p>
            @else
                <p class="mt-1 max-w-2xl text-sm text-slate-600">Generate a one-time code here, then enter it under Settings → Sitewell Static Frontend in WordPress. The code expires after ten minutes.</p>
            @endif
        </div>

        @if ($canManageWebsite)
            <div class="flex shrink-0 flex-wrap gap-2">
                <form method="POST" action="{{ route('admin.websites.wordpress.pairing-code', $website) }}">
                    @csrf
                    <button type="submit" @disabled(! $website->repository) class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800 disabled:cursor-not-allowed disabled:bg-slate-300">
                        {{ $wordpressConnected ? 'Reconnect plugin' : 'Generate connection code' }}
                    </button>
                </form>
                @if ($wordpressConnection && ($wordpressConnected || $wordpressConnection->pairing_code_expires_at?->isFuture()))
                    <form method="POST" action="{{ route('admin.websites.wordpress.connection.destroy', $website) }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="rounded-md border border-red-200 bg-white px-3 py-2 text-sm font-medium text-red-700 hover:bg-red-50">Revoke</button>
                    </form>
                @endif
                @if ($wordpressConnected && $website->repository)
                    <form method="POST" action="{{ route('admin.websites.wordpress.releases.store', $website) }}">
                        @csrf
                        <button type="submit" @disabled($latestRelease && in_array($latestRelease->status, ['queued', 'building'], true)) class="rounded-md bg-teal-700 px-3 py-2 text-sm font-medium text-white hover:bg-teal-600 disabled:cursor-not-allowed disabled:bg-slate-300">Deploy latest GitHub version</button>
                    </form>
                @endif
            </div>
        @endif
    </div>

    @unless ($website->repository)
        <p class="mt-4 rounded-md bg-amber-50 px-3 py-2 text-sm text-amber-800">Connect the website’s GitHub repository before connecting the WordPress plugin.</p>
    @endunless

    @if ($pairingCode)
        <div class="mt-4 rounded-lg border border-violet-200 bg-violet-50 p-4">
            <p class="text-sm font-medium text-violet-950">Enter this code in the WordPress plugin</p>
            <p class="mt-2 font-mono text-2xl font-semibold tracking-widest text-violet-950" aria-label="WordPress connection code">{{ $pairingCode }}</p>
            <p class="mt-2 text-xs text-violet-700">This code is shown once and expires in ten minutes. Generating another code invalidates it.</p>
        </div>
    @endif

    @if ($website->wordpressStaticReleases->isNotEmpty())
        <div class="mt-4 border-t border-slate-200 pt-4">
            <p class="text-sm font-medium text-slate-900">Recent static releases</p>
            <div class="mt-2 divide-y divide-slate-100">
                @foreach ($website->wordpressStaticReleases as $release)
                    <div class="flex flex-wrap items-center justify-between gap-2 py-2 text-sm">
                        <div>
                            <span class="font-mono text-xs text-slate-700">{{ $release->commit_sha ? Str::limit($release->commit_sha, 10, '') : $release->source_ref }}</span>
                            <span class="ml-2 text-slate-500">{{ $release->created_at->diffForHumans() }}</span>
                            @if ($release->error)
                                <p class="mt-1 max-w-2xl text-xs text-red-700">{{ $release->error }}</p>
                            @endif
                        </div>
                        <span @class([
                            'rounded-full px-2 py-0.5 text-xs font-medium',
                            'bg-emerald-100 text-emerald-800' => $release->status === 'ready' && $release->activated_at,
                            'bg-teal-100 text-teal-800' => $release->status === 'ready' && ! $release->activated_at,
                            'bg-amber-100 text-amber-800' => in_array($release->status, ['queued', 'building'], true),
                            'bg-red-100 text-red-800' => $release->status === 'failed',
                        ])>{{ $release->activated_at ? 'Live' : Str::headline($release->status) }}</span>
                    </div>
                @endforeach
            </div>
        </div>
    @endif
</section>
