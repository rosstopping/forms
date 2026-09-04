@extends('layouts.marketing')

@section('title', 'WordPress plugin')
@section('meta_description', 'Download Sitewell by Digizu for a secure connection between your WordPress website and Sitewell website care.')

@section('content')
    <section class="overflow-hidden border-b border-ink/10 py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-[1.15fr_.85fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">WordPress connection</p>
                <h1 class="mt-5 max-w-3xl font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">Sitewell by Digizu</h1>
                <p class="mt-6 max-w-2xl text-pretty text-lg leading-8 text-ink/65">Connect an existing WordPress website to Sitewell for managed website updates, SEO insights and ongoing site auditing—without losing access to WordPress administration.</p>

                <div class="mt-8 flex flex-col items-start gap-4 sm:flex-row sm:items-center">
                    <a href="{{ route('marketing.wordpress.download') }}" class="inline-flex min-h-12 items-center justify-center rounded-md bg-ink px-6 py-3 font-medium text-paper shadow-sm hover:bg-ink/90">Download the plugin</a>
                    <p class="text-sm text-ink/55">Version {{ $pluginVersion }} · {{ $pluginSize }} · ZIP</p>
                </div>

                <p class="mt-5 text-sm leading-6 text-ink/50">This plugin is privately distributed for Sitewell customers. Your website provider may wish to review and approve it before installation.</p>
            </div>

            <div class="rounded-2xl bg-ink p-8 text-paper shadow-xl sm:p-10">
                <div class="flex size-16 items-center justify-center rounded-xl bg-paper font-display text-2xl font-semibold text-ink">SW</div>
                <dl class="mt-8 grid gap-5 text-sm">
                    <div class="flex items-center justify-between gap-4 border-b border-paper/10 pb-5">
                        <dt class="text-paper/55">Current version</dt>
                        <dd class="font-medium">{{ $pluginVersion }}</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-paper/10 pb-5">
                        <dt class="text-paper/55">WordPress</dt>
                        <dd class="font-medium">6.6 or newer</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4 border-b border-paper/10 pb-5">
                        <dt class="text-paper/55">PHP</dt>
                        <dd class="font-medium">8.2 or newer</dd>
                    </div>
                    <div class="flex items-center justify-between gap-4">
                        <dt class="text-paper/55">Licence</dt>
                        <dd class="font-medium">GPLv2 or later</dd>
                    </div>
                </dl>
            </div>
        </div>
    </section>

    <section class="py-16 sm:py-24">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div class="max-w-2xl">
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Designed for safe handovers</p>
                <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A controlled connection to Sitewell</h2>
            </div>

            <div class="mt-12 grid gap-px overflow-hidden rounded-xl bg-ink/10 ring-1 ring-ink/10 md:grid-cols-3">
                <article class="bg-[#fffefa] p-7 sm:p-8">
                    <p class="font-mono text-sm text-garden">01</p>
                    <h3 class="mt-4 font-display text-2xl font-semibold tracking-tight">Verified updates</h3>
                    <p class="mt-3 leading-7 text-ink/65">Every release is downloaded over HTTPS and checked against its expected size and SHA-256 checksum before activation.</p>
                </article>
                <article class="bg-[#fffefa] p-7 sm:p-8">
                    <p class="font-mono text-sm text-garden">02</p>
                    <h3 class="mt-4 font-display text-2xl font-semibold tracking-tight">Restricted files</h3>
                    <p class="mt-3 leading-7 text-ink/65">Executable server files, hidden control files, unsafe paths and unsupported file types are rejected before extraction.</p>
                </article>
                <article class="bg-[#fffefa] p-7 sm:p-8">
                    <p class="font-mono text-sm text-garden">03</p>
                    <h3 class="mt-4 font-display text-2xl font-semibold tracking-tight">Immediate rollback</h3>
                    <p class="mt-3 leading-7 text-ink/65">Disabling the Sitewell website or deactivating the plugin returns visitors to the original WordPress website immediately.</p>
                </article>
            </div>
        </div>
    </section>

    <section class="border-y border-ink/10 bg-lichen/35 py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[.8fr_1.2fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Installation</p>
                <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight text-balance">From download to connected</h2>
                <p class="mt-5 text-pretty leading-7 text-ink/65">A WordPress administrator or hosting provider can complete the installation. Sitewell will provide the one-time connection code.</p>
            </div>

            <ol class="grid gap-4">
                @foreach ([
                    ['Download', 'Download the ZIP using the button above. Do not extract it.'],
                    ['Install', 'In WordPress, open Plugins > Add New Plugin > Upload Plugin and select the ZIP.'],
                    ['Activate', 'Activate Sitewell by Digizu, then open Settings > Sitewell by Digizu.'],
                    ['Connect', 'Enter the one-time connection code generated for the website in Sitewell.'],
                    ['Review', 'Check for the approved website update, enable the Sitewell website and select Save.'],
                ] as $step)
                    <li class="grid grid-cols-[auto_1fr] gap-4 rounded-xl bg-paper p-5 ring-1 ring-ink/10">
                        <span class="flex size-9 items-center justify-center rounded-full bg-ink font-mono text-sm text-paper">{{ $loop->iteration }}</span>
                        <div>
                            <h3 class="font-display text-xl font-semibold tracking-tight">{{ $step[0] }}</h3>
                            <p class="mt-1 leading-6 text-ink/65">{{ $step[1] }}</p>
                        </div>
                    </li>
                @endforeach
            </ol>
        </div>
    </section>

    <section class="py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">What it shares</p>
                <h2 class="mt-4 font-display text-4xl font-semibold tracking-tight">Clear by design</h2>
                <p class="mt-5 leading-7 text-ink/65">Once an administrator chooses to connect, the plugin sends the website URL, plugin version and connection credential needed to communicate with Sitewell. Update checks also include the currently active release identifier.</p>
                <p class="mt-4 leading-7 text-ink/65">It does not send WordPress passwords, WordPress user details, form submissions or visitor analytics, and it does not add visitor tracking.</p>
                <div class="mt-6 flex flex-wrap gap-x-5 gap-y-2 text-sm">
                    <a href="{{ route('marketing.privacy') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Privacy policy</a>
                    <a href="{{ route('marketing.terms') }}" class="font-medium text-garden underline decoration-garden/30 underline-offset-4 hover:decoration-garden">Terms of service</a>
                </div>
            </div>

            <div class="rounded-xl border border-ink/10 p-7 sm:p-8">
                <p class="font-mono text-xs uppercase tracking-wide text-ink/45">SHA-256 checksum</p>
                <code class="mt-4 block break-all rounded-lg bg-ink p-4 font-mono text-sm leading-6 text-paper">{{ $pluginChecksum }}</code>
                <p class="mt-4 text-sm leading-6 text-ink/55">Technical teams can compare this checksum with the downloaded ZIP to confirm the file has not changed in transit.</p>
            </div>
        </div>
    </section>

    <section class="bg-ink py-16 text-paper sm:py-20">
        <div class="mx-auto flex max-w-7xl flex-col items-start justify-between gap-8 px-5 sm:px-8 lg:flex-row lg:items-center lg:px-10">
            <div>
                <h2 class="font-display text-4xl font-semibold tracking-tight">Need help with installation?</h2>
                <p class="mt-3 text-paper/65">We’re happy to answer technical or security questions from you or your website provider.</p>
            </div>
            <a href="mailto:sitewell@digizu.co.uk" class="inline-flex min-h-12 items-center justify-center rounded-md bg-paper px-6 py-3 font-medium text-ink hover:bg-paper/90">sitewell@digizu.co.uk</a>
        </div>
    </section>
@endsection
