<div><h2 class="text-lg font-semibold text-balance text-slate-950">Content connections</h2><p class="mt-1 text-base text-pretty text-slate-600 sm:text-sm">Choose how prepared changes reach your website.</p></div>
@if ($hasContentDeliveryConnection)
    <div class="grid gap-4 sm:grid-cols-3">
        <div class="border-t border-slate-950/10 pt-4"><h3 class="font-medium text-slate-950">Sitewell Pixel</h3><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $website->pixel_last_seen_at ? 'Receiving website activity.' : 'No website activity received yet.' }}</p>@if (config('forms.pixel_ui_enabled') && $website->pixel_enabled)<p class="mt-3 text-sm"><a href="{{ route('admin.websites.section', [$website, 'pixel']) }}" class="font-medium underline">Open Pixel</a></p>@endif</div>
        <div class="border-t border-slate-950/10 pt-4"><h3 class="font-medium text-slate-950">WordPress</h3><p class="mt-1 text-base text-slate-600 sm:text-sm">{{ $website->wordpressConnection?->isConnected() ? 'Connected.' : 'Not connected.' }}</p>@if ($website->wordpress_enabled)<p class="mt-3 text-sm"><a href="{{ route('admin.websites.section', [$website, 'wordpress']) }}" class="font-medium underline">Open WordPress</a></p>@endif</div>
        <div class="border-t border-slate-950/10 pt-4"><h3 class="font-medium text-slate-950">GitHub</h3><p class="mt-1 break-words text-base text-slate-600 sm:text-sm">{{ $website->repository?->full_name ?: 'Not connected.' }}</p></div>
    </div>
@endif
        @if ($canUseGrowthFeatures && ! $hasContentDeliveryConnection)
            <section class="overflow-hidden rounded-xl border border-violet-200 bg-white" aria-labelledby="content-connection-title">
                <div class="bg-violet-50 px-5 py-6 sm:px-6">
                    <p class="text-sm font-medium text-violet-700">Connect your website</p>
                    <h2 id="content-connection-title" class="mt-2 text-xl font-semibold tracking-tight text-slate-950">Choose how Sitewell prepares website changes</h2>
                    <p class="mt-2 max-w-3xl text-sm leading-6 text-slate-600">Your Sitewell package includes three ways to connect your website. A Sitewell specialist will help you choose the safest option for your setup and get it connected.</p>
                </div>
                <div class="grid gap-4 p-5 sm:grid-cols-3 sm:p-6">
                    <article class="rounded-lg border border-slate-950/10 p-4">
                        <p class="text-sm font-medium text-teal-700">Option 1</p>
                        <h3 class="mt-2 font-semibold text-slate-950">Sitewell Pixel</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">A lightweight connection for supported page updates without replacing your website platform.</p>
                    </article>
                    <article class="rounded-lg border border-slate-950/10 p-4">
                        <p class="text-sm font-medium text-teal-700">Option 2</p>
                        <h3 class="mt-2 font-semibold text-slate-950">WordPress</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Connect your WordPress website so our specialists can prepare and manage compatible changes.</p>
                    </article>
                    <article class="rounded-lg border border-slate-950/10 p-4">
                        <p class="text-sm font-medium text-teal-700">Option 3</p>
                        <h3 class="mt-2 font-semibold text-slate-950">GitHub</h3>
                        <p class="mt-2 text-sm leading-6 text-slate-600">Link the website repository for larger content and code changes delivered through a reviewable workflow.</p>
                    </article>
                </div>
                <div class="flex flex-col gap-4 border-t border-slate-950/10 bg-slate-50 px-5 py-5 sm:flex-row sm:items-center sm:justify-between sm:px-6">
                    <div>
                        <h3 class="text-sm font-semibold text-slate-950">Not sure which connection suits your website?</h3>
                        <p class="mt-1 text-sm text-slate-600">Book a free call with support to discuss the options and arrange the setup.</p>
                    </div>
                    <a href="{{ $contentSupportCallUrl }}" target="_blank" rel="noreferrer" class="inline-flex shrink-0 items-center justify-center rounded-lg bg-slate-900 px-4 py-2.5 text-sm font-semibold text-white hover:bg-slate-800 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-slate-900">Book a call with support <span class="ml-2" aria-hidden="true">→</span></a>
                </div>
            </section>
        @endif

        @if (Auth::user()?->isAdmin())
        <div class="rounded-lg border border-slate-950/10 bg-white p-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <p class="text-sm font-medium text-slate-500">Content publishing</p>
                    <h2 class="mt-1 font-semibold">GitHub repository</h2>
                    @if ($website->repository)
                        <p class="mt-1 text-sm text-slate-600">
                            <span class="font-medium text-slate-900">{{ $website->repository->full_name }}</span>
                            on {{ $website->repository->default_branch }}
                            @if ($website->repository->project_path)
                                · {{ $website->repository->project_path }}
                            @endif
                        </p>
                        <p class="mt-1 text-sm text-slate-500">Installed for {{ $website->repository->installation->account_login }}.</p>
                    @else
                        <p class="mt-1 text-sm text-slate-600">Connect the website's source repository before adding requests or generating content.</p>
                    @endif
                </div>
                <div class="flex flex-wrap gap-2">
                    <a href="{{ route('admin.website-repositories.create', $website) }}" class="rounded-md bg-slate-900 px-3 py-2 text-sm font-medium text-white hover:bg-slate-800">{{ $website->repository ? 'Change repository' : 'Connect GitHub' }}</a>
                    @if ($website->repository)
                        <form method="POST" action="{{ route('admin.website-repositories.destroy', $website) }}">
                            @csrf
                            @method('DELETE')
                            <button type="submit" class="rounded-md border border-slate-950/10 bg-white px-3 py-2 text-sm font-medium text-slate-700 hover:bg-slate-50">Disconnect</button>
                        </form>
                    @endif
                </div>
            </div>
        </div>
        @endif

