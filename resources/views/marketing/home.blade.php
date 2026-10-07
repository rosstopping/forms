@extends('layouts.marketing')

@section('title', 'Managed SEO and website care for UK businesses')
@section('meta_description', 'Sitewell fixes and looks after your website, then works on its Google and AI search visibility. Show us your website to see where we’d start.')
@section('structured_data')
    @php
        $homeUrl = route('marketing.home');
        $structuredData = [
            '@context' => 'https://schema.org',
            '@graph' => [
                [
                    '@type' => 'Organization',
                    '@id' => $homeUrl.'#organization',
                    'name' => 'Sitewell',
                    'url' => $homeUrl,
                    'description' => 'Managed website and SEO service for UK businesses.',
                    'telephone' => '+441302248374',
                    'areaServed' => ['@type' => 'Country', 'name' => 'United Kingdom'],
                ],
                [
                    '@type' => 'WebSite',
                    '@id' => $homeUrl.'#website',
                    'name' => 'Sitewell',
                    'url' => $homeUrl,
                    'publisher' => ['@id' => $homeUrl.'#organization'],
                    'inLanguage' => 'en-GB',
                ],
            ],
        ];
    @endphp
    <script type="application/ld+json">@json($structuredData, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endsection

@section('content')
    <section class="bg-white px-3 pt-1 pb-6 text-[#151618] sm:px-6 sm:pt-2 sm:pb-8" aria-labelledby="hero-spotlight">
        <div class="mx-auto grid max-w-7xl justify-items-center gap-6 rounded-3xl bg-[#faf7f4] px-5 py-10 text-center sm:gap-8 sm:px-10 sm:py-12 lg:py-16">
            <p class="flex max-w-full items-center gap-2.5 rounded-full bg-[#fafaf9] py-2 pr-4 pl-2 text-sm md:text-base font-medium text-[#151618] ring-1 ring-black/8"><span class="size-5 md:size-6 shrink-0 rounded-full border-[5px] md:border-[6px] border-[#ff5035] bg-white" aria-hidden="true"></span>We’ll get you more customers.</p>
            <h1 id="hero-spotlight" class="max-w-[19ch] text-4xl font-medium leading-[1.12] tracking-tight text-balance sm:text-6xl lg:text-7xl">Turn more searches into <span class="underline decoration-[#d63d24]/50 decoration-2 underline-offset-8 sm:decoration-4">your next customer.</span></h1>
            <p class="w-full max-w-3xl text-pretty text-base text-[#62666d] sm:text-xl">Your customers search Google, ask ChatGPT and read the sites that shape AI answers. We find the searches that count and help you get found.</p>
            <div class="grid w-full max-w-xl justify-items-center gap-2">
                <form method="POST" action="{{ route('marketing.free-site-audit.store') }}" data-audit-form data-marketing-attribution="{{ json_encode($attribution) }}" class="grid w-full gap-3">
                    @csrf
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center rounded-full bg-white p-1.5 ring-1 ring-black/15 focus-within:ring-2 focus-within:ring-[#d63d24]">
                        <label for="hero-website-url" class="sr-only">Website address</label>
                        <input id="hero-website-url" name="website_url" type="text" required maxlength="255" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="example.com" value="{{ old('website_url') }}" aria-invalid="{{ $errors->has('website_url') ? 'true' : 'false' }}" @error('website_url') aria-describedby="hero-website-error" @enderror class="min-h-14 w-full min-w-0 rounded-full border-0 bg-transparent px-3 py-4 text-base text-[#151618] placeholder:text-[#62666d] focus:outline-none sm:px-5">
                        <button type="submit" aria-label="Get your free search audit" class="inline-flex min-h-14 items-center justify-center gap-3 rounded-full bg-[#d63d24] py-4 pr-4 pl-5 font-medium text-white hover:bg-[#b9301b] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d63d24]"><span class="sm:hidden">Free audit</span><span class="max-sm:hidden">Get your free search audit</span><span class="shrink-0" aria-hidden="true">→</span></button>
                    </div>
                    @error('website_url')<p id="hero-website-error" role="alert" class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    <div class="absolute left-[-9999px] size-px overflow-hidden" aria-hidden="true">
                        <label for="hero-sitewell-check">Leave this field empty</label>
                        <input id="hero-sitewell-check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off">
                    </div>
                    @if ($turnstileEnabled)
                        <div class="cf-turnstile justify-self-center" data-sitekey="{{ $turnstileSiteKey }}" data-theme="light" data-size="flexible"></div>
                        @error('cf-turnstile-response')<p role="alert" class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    @endif
                </form>
            </div>
        </div>
        <div data-home-reveal="fade" class="mx-auto grid max-w-5xl justify-items-center gap-6 px-5 pt-8 sm:pt-10">
            <p class="text-center text-base text-[#62666d] sm:text-sm">Where people look for answers.</p>
            <ul role="list" aria-label="Search engines and AI assistants" class="grid w-full grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([['google', 'Google'], ['bing', 'Bing'], ['openai', 'ChatGPT'], ['gemini', 'Gemini'], ['perplexity', 'Perplexity'], ['claude', 'Claude']] as [$mark, $name])
                    <li class="flex items-center justify-center gap-2.5 text-lg font-medium tracking-tight text-[#62666d]">
                        <img src="{{ asset('search-'.$mark.'.svg') }}" alt="{{ $name }} logo" width="24" height="24" class="size-6 shrink-0 opacity-60" decoding="async">
                        <span aria-hidden="true">{{ $name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="py-8 text-[#151618] sm:py-12" aria-labelledby="homepage-founder">
        <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:px-10">
            <div data-home-reveal class="flex items-start gap-4">
                <img src="{{ asset('ross-topping.jpg') }}" alt="Ross Topping, founder of Sitewell" width="80" height="80" class="size-16 shrink-0 rounded-full object-cover sm:size-20" decoding="async">
                <div class="grid gap-2">
                    <h2 id="homepage-founder" class="text-2xl font-medium tracking-tight sm:text-3xl">Hi, I’m Ross.</h2>
                    <p class="text-base text-[#62666d]">Founder & Web Developer. Based in Doncaster.</p>
                    <p class="text-base font-medium">01302 248 374</p>
                </div>
            </div>
            <div data-home-reveal class="grid content-start gap-4">
                <p class="max-w-[48ch] text-pretty text-xl">I review the findings, agree the priorities with you and oversee the work. You’ll know who’s looking after your website.</p>
                <p class="max-w-[56ch] text-pretty text-lg text-[#62666d]">The free check gives us a starting point. If you’d like us to take care of the improvements, we’ll agree the work, arrange access and get started on the first fixes.</p>
            </div>
        </div>
    </section>

    <section class="py-12 text-[#151618] sm:py-16" aria-labelledby="homepage-services">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:px-10">
            <div data-home-reveal class="grid content-start justify-items-start gap-5">
                <h2 id="homepage-services" class="max-w-[20ch] text-3xl font-medium tracking-tight text-balance sm:text-4xl">The improvements, done for you.</h2>
                <p class="max-w-[40ch] text-pretty text-lg text-[#62666d]">You don’t need to learn another tool or organise the fixes yourself. We agree the priorities and do the work included in your plan.</p>
            </div>
            <dl class="grid divide-y divide-black/10">
                @foreach ([
                    ['Fix website problems.', 'We resolve technical issues that make your website harder to find or use, and check the changes work.'],
                    ['Improve existing pages.', 'We make your service pages clearer and more useful, so customers can understand what you offer and how to enquire.'],
                    ['Create useful content.', 'We write pages and articles around the questions your customers ask, as part of your agreed search and content work.'],
                ] as [$heading, $copy])
                    <div data-home-reveal class="grid gap-2 py-6 first:pt-0 last:pb-0">
                        <dt class="text-xl font-medium tracking-tight">{{ $heading }}</dt>
                        <dd class="max-w-[48ch] text-pretty text-lg text-[#62666d]">{{ $copy }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="py-12 text-[#151618] sm:py-16" aria-labelledby="homepage-progress">
        <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-[4fr_7fr] lg:px-10">
            <div data-home-reveal>
                <h2 id="homepage-progress" class="max-w-[22ch] text-3xl font-medium tracking-tight text-balance sm:text-4xl">See what’s changed. Know what comes next.</h2>
            </div>
            <div data-home-reveal class="grid content-start justify-items-start gap-5">
                <p class="max-w-[52ch] text-pretty text-lg text-[#62666d]">You get clear updates on completed work, the next priorities and how your search visibility is changing. We keep looking after your website, with ongoing SEO and content improvements at the level of support you choose.</p>
                <p class="text-base font-medium"><a href="{{ route('marketing.free-site-audit') }}" class="inline-flex min-h-12 items-center gap-3 rounded-full px-5 py-3 ring-1 ring-black/20 hover:bg-[#faf7f4] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d63d24]">Get your free search audit <span aria-hidden="true">→</span></a></p>
            </div>
        </div>
    </section>
@if ($turnstileEnabled)
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
@endsection
