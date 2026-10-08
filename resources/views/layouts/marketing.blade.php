<!DOCTYPE html>
<html lang="en-GB" class="antialiased">
<head>
    <x-marketing.tag-manager />
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#ffffff">
    <link rel="icon" href="{{ asset('favicon.ico') }}" sizes="16x16 32x32 48x48">
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}" sizes="180x180">
    <meta name="description" content="@yield('meta_description', 'Sitewell manages website and SEO improvements for UK businesses.')">
    @if (request()->routeIs('marketing.website-audits.*', 'admin.onboarding.audits.show'))
        <meta name="robots" content="noindex">
    @else
        <link rel="canonical" href="{{ request()->url() }}">
    @endif
    <meta property="og:site_name" content="Sitewell">
    <meta property="og:locale" content="en_GB">
    <meta property="og:type" content="@yield('og_type', 'website')">
    <meta property="og:title" content="@yield('title', 'Sitewell'){{ $__env->hasSection('concise_title') ? '' : ' · Sitewell' }}">
    <meta property="og:description" content="@yield('meta_description', 'Sitewell manages website and SEO improvements for UK businesses.')">
    <meta property="og:url" content="{{ request()->url() }}">
    <meta name="twitter:card" content="summary">
    <meta name="twitter:title" content="@yield('title', 'Sitewell'){{ $__env->hasSection('concise_title') ? '' : ' · Sitewell' }}">
    <meta name="twitter:description" content="@yield('meta_description', 'Sitewell manages website and SEO improvements for UK businesses.')">
    <title>@yield('title', 'Sitewell'){{ $__env->hasSection('concise_title') ? '' : ' · Sitewell' }}</title>
    @fonts
    @yield('structured_data')
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @if (request()->routeIs('marketing.home', 'marketing.free-site-audit', 'marketing.website-audits.show', 'marketing.website-audits.full', 'admin.onboarding.audits.show'))
        @vite('resources/js/marketing-events.js')
    @endif
</head>
<body class="marketing-site prospect-workspace min-h-dvh bg-white text-ink">
    <a href="#main-content" class="sr-only focus:not-sr-only focus:fixed focus:top-4 focus:left-4 focus:z-50 focus:rounded-full focus:bg-white focus:px-5 focus:py-3 focus:text-ink focus:shadow-lg focus:outline-2 focus:outline-offset-2 focus:outline-garden">Skip to content</a>
    <x-marketing.tag-manager noscript />
    <div class="isolate min-h-dvh">
        @if (request()->routeIs('marketing.pricing') && \App\Support\MembershipPlan::activeGrowthOffer())
            <a href="{{ route('marketing.pricing') }}" class="flex items-center justify-center gap-2 bg-garden px-5 py-2.5 text-center text-sm font-medium text-white hover:bg-moss"><span>2026 Growth offer: save 20% — now £316/month</span><span aria-hidden="true">→</span></a>
        @endif
        <header class="bg-white text-ink">
            <div class="mx-auto flex max-w-7xl items-center justify-between gap-5 px-5 py-4 sm:px-8 sm:py-5 lg:px-10">
                <p class="shrink-0 text-2xl font-semibold tracking-tight sm:text-3xl"><a href="{{ route('marketing.home') }}" aria-label="Homepage">sitewell<span class="text-garden">.</span></a></p>
                <nav aria-label="Main navigation">
                    <a href="{{ route('marketing.free-site-audit') }}" class="inline-flex min-h-12 items-center justify-center gap-2 rounded-full px-4 py-3 text-center text-base font-medium ring-1 ring-black/20 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden sm:gap-3 sm:px-5 sm:text-sm"><span class="sm:hidden">Free Audit</span><span class="max-sm:hidden">Get your free search audit</span><span aria-hidden="true">→</span></a>
                </nav>
            </div>
        </header>

        <main id="main-content" tabindex="-1">
            @yield('content')
        </main>

        <footer class="border-t border-black/10 bg-white text-ink">
            <div class="mx-auto flex max-w-7xl flex-col gap-6 px-5 py-8 sm:px-8 lg:flex-row lg:items-center lg:justify-between lg:px-10">
                <p class="text-base text-ink/65 sm:text-sm">© {{ now()->year }} Sitewell.</p>
                <nav aria-label="Footer navigation" class="flex flex-wrap gap-x-6 gap-y-2 text-base text-ink/65 sm:text-sm">
                    <a href="{{ route('marketing.about') }}" class="inline-flex min-h-12 items-center hover:text-ink">About</a>
                    <a href="{{ route('marketing.faqs') }}" class="inline-flex min-h-12 items-center hover:text-ink">FAQs</a>
                    <a href="{{ route('marketing.journal') }}" class="inline-flex min-h-12 items-center hover:text-ink">Journal</a>
                    <a href="{{ route('marketing.privacy') }}" class="inline-flex min-h-12 items-center hover:text-ink">Privacy policy</a>
                    <a href="{{ route('marketing.terms') }}" class="inline-flex min-h-12 items-center hover:text-ink">Terms of service</a>
                </nav>
            </div>
        </footer>

    </div>
</body>
</html>
