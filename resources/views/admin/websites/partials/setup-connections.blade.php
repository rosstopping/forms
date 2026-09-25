<div class="mb-8 space-y-5">
    @if (in_array($step, ['connection', 'review']))
        <div class="grid gap-4 border-b border-slate-950/10 pb-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
            <div class="min-w-0">
                <h3 class="font-medium text-slate-950">GitHub repository</h3>
                <p class="mt-1 break-words text-base text-slate-600 sm:text-sm">{{ $website->repository ? $website->repository->full_name.' · '.$website->repository->default_branch : 'Not connected' }}</p>
                @if ($website->repository && $website->repository->installation?->status !== 'active')<p class="mt-1 text-base text-amber-800 sm:text-sm">The GitHub installation needs reconnecting.</p>@endif
            </div>
            <a href="{{ route('admin.website-repositories.create', $website) }}" target="_blank" rel="noopener" class="ui-button ui-button-secondary">{{ $website->repository ? 'Manage repository' : 'Connect GitHub' }} ↗</a>
        </div>
        <div class="grid gap-4 border-b border-slate-950/10 pb-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
            <div>
                <h3 class="font-medium text-slate-950">WordPress</h3>
                <p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $website->wordpressConnection?->isConnected() ? 'Plugin connected' : ($website->wordpressConnection?->pairing_code_expires_at?->isFuture() ? 'Waiting for WordPress to pair' : 'Not connected') }}</p>
                <p class="mt-2 max-w-[60ch] text-base text-pretty text-slate-500 sm:text-sm">The current WordPress integration serves the website built from GitHub. Connect the repository first, then install the plugin and enter a connection code in WordPress.</p>
                @if ($step === 'connection')<p class="mt-3 text-base sm:text-sm"><a href="{{ route('marketing.wordpress.download') }}" class="text-teal-700 underline underline-offset-4">Download the WordPress plugin</a></p>@endif
            </div>
            @if ($step === 'connection')
                <form method="POST" action="{{ route('admin.website-setup.pairing', $website) }}">
                    @csrf
                    <button type="submit" class="ui-button ui-button-secondary" @disabled(! $website->repository)>{{ $website->wordpressConnection?->isConnected() ? 'Create new pairing code' : 'Create pairing code' }}</button>
                </form>
            @endif
        </div>
        @if (session('wordpress_pairing_code'))
            <div class="ui-well p-4">
                <p class="text-base text-slate-700 sm:text-sm">Enter this code under Settings → Sitewell Static Frontend in WordPress. It expires in ten minutes.</p>
                <p class="mt-3 font-mono text-2xl font-medium tracking-wide">{{ session('wordpress_pairing_code') }}</p>
            </div>
        @endif
        @if (config('forms.pixel_ui_enabled'))
            <div class="grid gap-4 border-b border-slate-950/10 pb-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
                <div><h3 class="font-medium">Sitewell Pixel</h3><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $website->pixel_last_seen_at ? 'Last activity '.$website->pixel_last_seen_at->diffForHumans() : 'No activity received' }}</p><p class="mt-2 text-base text-slate-500 sm:text-sm">Supports compatible page updates. Scheduled content still requires a repository.</p></div>
                <a href="{{ route('admin.websites.section', [$website, 'pixel']) }}" target="_blank" rel="noopener" class="ui-button ui-button-secondary">Pixel setup ↗</a>
            </div>
        @endif
    @endif
    @if ($step === 'connection')
        <p class="text-base text-slate-600 sm:text-sm">If the repository is already available but your GitHub automation access is missing, <a href="{{ route('admin.github.connect', $website) }}" target="_blank" rel="noopener" class="text-teal-700 underline underline-offset-4">connect your GitHub account ↗</a>.</p>
    @endif
    @if (in_array($step, ['google', 'review']))
        @php
            $searchConnection = $website->searchConsoleConnection;
            $searchConnected = filled($searchConnection?->property_url) && filled($searchConnection?->refresh_token) && ! $searchConnection?->access_denied_at;
            $profileConnected = filled($website->businessProfileConnection?->location_name) && filled($website->businessProfileConnection?->refresh_token);
        @endphp
        <div class="grid gap-4 border-b border-slate-950/10 pb-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
            <div class="min-w-0"><h3 class="font-medium">Google Search Console</h3><p class="mt-1 break-words text-base text-slate-600 sm:text-sm">{{ $searchConnected ? 'Property selected: '.$searchConnection->property_url : 'Connect Google and select a matching property' }}</p></div>
            <a href="{{ route($searchConnected ? 'admin.search-console.property' : 'admin.search-console.connect', $website) }}" target="_blank" rel="noopener" class="ui-button ui-button-secondary">{{ $searchConnected ? 'Manage property' : 'Connect Search Console' }} ↗</a>
        </div>
        <div class="grid gap-4 border-b border-slate-950/10 pb-5 sm:grid-cols-[minmax(0,1fr)_auto] sm:items-start">
            <div class="min-w-0"><h3 class="font-medium">Google Business Profile</h3><p class="mt-1 break-words text-base text-slate-600 sm:text-sm">{{ $profileConnected ? 'Location selected: '.$website->businessProfileConnection->location_title : 'No location connected' }}</p></div>
            <a href="{{ route($profileConnected ? 'admin.business-profile.locations' : 'admin.business-profile.connect', $website) }}" target="_blank" rel="noopener" class="ui-button ui-button-secondary">{{ $profileConnected ? 'Manage location' : 'Connect Business Profile' }} ↗</a>
        </div>
    @endif
    <p class="text-base text-pretty text-slate-500 sm:text-sm">Links marked ↗ open in a new tab so this setup stays open. After connecting, <a href="{{ route('admin.website-setup.edit', [$website, 'step' => $step]) }}" class="text-teal-700 underline underline-offset-4">refresh connection status</a> before entering other answers. Status reflects saved connections, not a fresh API access check.</p>
</div>
