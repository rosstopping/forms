@extends('layouts.marketing')
@section('title', 'SEO outsourcing for web agencies | Sitewell')
@section('concise_title', '1')
@section('meta_description', 'Offer fully managed SEO and website care to your agency’s clients. Ross handles the work; you set your prices and keep the relationship. Book a quick chat.')
@section('structured_data')
    @include('marketing.agencies.schema')
    @vite('resources/js/marketing-events.js')
@endsection
@section('content')
    <section class="bg-white px-3 pt-1 pb-6 text-[#151618] sm:px-6 sm:pt-2 sm:pb-8" aria-labelledby="agency-hero">
        <div class="mx-auto grid max-w-7xl justify-items-center gap-6 rounded-3xl bg-[#faf7f4] px-5 py-10 text-center sm:gap-8 sm:px-10 sm:py-12 lg:py-16">
            <p class="flex max-w-full items-center gap-2.5 rounded-full bg-[#fafaf9] py-2 pr-4 pl-2 text-sm font-medium text-[#151618] ring-1 ring-black/8 md:text-base"><span class="size-5 shrink-0 rounded-full border-[5px] border-[#ff5035] bg-white md:size-6 md:border-[6px]" aria-hidden="true"></span>For web designers, freelancers and agencies</p>
            <h1 id="agency-hero" class="max-w-[19ch] text-4xl font-medium leading-[1.12] tracking-tight text-balance sm:text-6xl lg:text-7xl">Offer SEO to your clients. <span class="underline decoration-[#d63d24]/50 decoration-2 underline-offset-8 sm:decoration-4">We’ll do the work.</span></h1>
            <p class="w-full max-w-3xl text-pretty text-base text-[#62666d] sm:text-xl">Already building websites for clients? I provide white-label SEO services so you can offer ongoing SEO without hiring anyone or taking on the extra workload. You keep your clients, set your own prices and build recurring revenue.</p>
            <div class="grid w-full max-w-xl justify-items-center gap-3">
                <p id="agency-audit-description" class="text-base text-[#62666d] sm:text-sm">Try a client’s website to see where we’d start.</p>
                <form method="POST" action="{{ route('marketing.free-site-audit.store') }}" aria-describedby="agency-audit-description" data-audit-form data-marketing-attribution="{{ json_encode($attribution) }}" class="grid w-full gap-3">
                    @csrf
                    <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center rounded-full bg-white p-1.5 ring-1 ring-black/15 focus-within:ring-2 focus-within:ring-[#d63d24]">
                        <label for="hero-website-url" class="sr-only">Client website address</label>
                        <input id="hero-website-url" name="website_url" type="text" required maxlength="255" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="clientwebsite.com" value="{{ old('website_url') }}" aria-invalid="{{ $errors->has('website_url') ? 'true' : 'false' }}" @error('website_url') aria-describedby="hero-website-error" @enderror class="min-h-14 w-full min-w-0 rounded-full border-0 bg-transparent px-3 py-4 text-base text-[#151618] placeholder:text-[#62666d] focus:outline-none sm:px-5">
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
            <div class="flex flex-wrap items-center justify-center gap-6">
                <a data-audit-book-call href="{{ route('marketing.ppc.book') }}" class="inline-flex min-h-12 items-center gap-3 rounded-full bg-white py-2 pr-5 pl-2 font-medium ring-1 ring-black/15 hover:bg-[#fafaf9] focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-[#d63d24]"><img src="{{ asset('ross-topping.jpg') }}" alt="" width="32" height="32" class="size-8 rounded-full object-cover">Book a quick chat <span aria-hidden="true">↗</span></a>
                <a href="#how-it-works" class="inline-flex min-h-12 items-center text-sm font-medium underline decoration-ink/25 underline-offset-4 hover:text-garden">See how it works</a>
            </div>
        </div>
        <div class="mx-auto grid max-w-5xl justify-items-center gap-6 px-5 pt-8 sm:pt-10">
            <p class="text-center text-base text-[#62666d] sm:text-sm">Where your clients’ websites should show up.</p>
            <ul role="list" aria-label="Search platforms" class="grid w-full grid-cols-2 gap-x-6 gap-y-6 sm:grid-cols-3 lg:grid-cols-6">
                @foreach ([['google', 'Google'], ['bing', 'Bing'], ['openai', 'ChatGPT'], ['gemini', 'Gemini'], ['perplexity', 'Perplexity'], ['claude', 'Claude']] as [$mark, $name])
                    <li class="flex items-center justify-center gap-2.5 text-lg font-medium tracking-tight text-[#62666d]">
                        <img src="{{ asset('search-'.$mark.'.svg') }}" alt="{{ $name }} logo" width="24" height="24" class="size-6 shrink-0 opacity-60" decoding="async">
                        <span aria-hidden="true">{{ $name }}</span>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>
    <section aria-labelledby="ross-introduction" class="py-14 sm:py-20">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-5 sm:px-8 lg:grid-cols-[3fr_2fr] lg:px-10">
            <figure class="flex aspect-video flex-col items-center justify-center gap-4 rounded-3xl bg-lichen px-6 text-center ring-1 ring-ink/10">
                <img src="{{ asset('ross-topping.jpg') }}" alt="Ross Topping, founder of Sitewell" width="96" height="96" class="size-24 rounded-full object-cover" loading="lazy">
                <div><p class="text-lg font-medium">A quick introduction from Ross</p><p class="mt-2 text-sm text-ink/60">60–90 seconds · Video coming soon</p></div>
                <figcaption class="max-w-sm text-sm/6 text-ink/60">Meet the person behind Sitewell and see how we could work together.</figcaption>
            </figure>
            <div>
                <h2 id="ross-introduction" class="text-3xl font-medium tracking-tight sm:text-4xl">Hi, I’m Ross.</h2>
                <p class="mt-5 text-base/7 text-ink/70">I’m a web developer from Doncaster. I’ve been building and managing websites for over 10 years, and now help other developers and agencies offer SEO without taking on all the extra work.</p>
                <p class="mt-4 text-base/7 text-ink/70">You deal directly with me. I handle the SEO, keep you updated and help you explain the progress to your clients.</p>
                <p class="mt-4 text-base/7 text-ink/70">You keep the relationship. I take care of the work.</p>
            </div>
        </div>
    </section>
    <section id="how-it-works" class="scroll-mt-8 border-y border-ink/10 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-10">
            <h2 class="text-3xl font-medium tracking-tight sm:text-4xl">Send me the website. I’ll take it from there.</h2>
            <ol class="mt-10 grid gap-8 md:grid-cols-3">
                @foreach ([['01', 'You introduce your clients.', 'I handle onboarding and arrange the access needed.'], ['02', 'We handle their ongoing SEO.', 'Website health, SEO, content improvements and ongoing reporting.'], ['03', 'You earn recurring revenue.', 'Offer the service under your own brand and set your customer’s price.']] as [$number, $heading, $copy])
                    <li class="border-t border-ink/20 pt-5"><p class="text-sm font-medium text-garden">{{ $number }}</p><h3 class="mt-3 text-xl font-medium">{{ $heading }}</h3><p class="mt-3 text-base/7 text-ink/70">{{ $copy }}</p></li>
                @endforeach
            </ol>
        </div>
    </section>
    <section id="available-now" class="scroll-mt-8 py-14 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div><p class="text-sm font-medium text-garden">Fully managed, with me behind it</p><h2 class="mt-4 text-3xl font-medium tracking-tight sm:text-4xl">The work behind your SEO service.</h2></div>
            <ul class="divide-y divide-ink/10">
                @foreach ([['Technical SEO and website care', 'Website audits, practical fixes and ongoing technical SEO improvements.'], ['Regular content improvements', 'Up to three scheduled content improvements per week, prepared for review. Existing-page optimisation, new articles and landing pages.'], ['Keyword and competitor research', 'Find the searches that matter to your clients, monitor Google rankings and use weekly competitor research to guide improvements.'], ['Google Business Profile management', 'Profile health checks and recommended changes, with Google posts and customer review replies prepared for approval.'], ['Reporting and support', 'Clear weekly reports, advanced lead handling and a dedicated website and SEO specialist.']] as [$heading, $copy])
                    <li class="py-5 first:pt-0"><h3 class="text-lg font-medium">{{ $heading }}</h3><p class="mt-2 text-base/7 text-ink/70">{{ $copy }}</p></li>
                @endforeach
            </ul>
        </div>
    </section>
    <section class="bg-lichen py-14 sm:py-20">
        <div class="mx-auto grid max-w-6xl items-center gap-10 px-5 sm:px-8 lg:grid-cols-2 lg:px-10">
            <div><h2 class="max-w-[24ch] text-balance text-3xl font-medium tracking-tight sm:text-4xl">A monthly service for clients who already trust you.</h2><p class="mt-5 max-w-lg text-base/7 text-ink/70">You’ve already built the relationship. Ongoing SEO gives you another useful service to offer after the website goes live.</p><p class="mt-4 max-w-lg text-base/7 text-ink/70">You set your client’s price. We agree Sitewell’s fee and the work included. The difference contributes to your margin, before your own costs.</p></div>
            <div class="border-l-2 border-garden pl-6"><p class="text-2xl font-medium tracking-tight">Your clients. Your pricing.</p><p class="mt-4 max-w-md text-base/7 text-ink/70">Start with one suitable website and build from there. You don’t need to hire an SEO specialist or manage the delivery yourself.</p></div>
        </div>
    </section>
    <section class="py-14 sm:py-20">
        <div class="mx-auto grid max-w-6xl gap-8 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <h2 class="text-3xl font-medium tracking-tight sm:text-4xl">A few useful answers.</h2>
            <div class="divide-y divide-ink/15 border-y border-ink/15">
                @foreach ([['Do I need SEO experience?', 'No. You bring your knowledge of the client. I handle the technical work, priorities and ongoing delivery.'], ['Can you look after my existing clients?', 'Yes. Send me their website and I’ll handle onboarding, including checking the setup and arranging the access needed.'], ['Who communicates with my customers?', 'You do. You keep the client relationship, pricing and customer communication. I support you behind the scenes.'], ['Do you make the website changes?', 'Yes. I handle the agreed technical SEO fixes, page improvements and new content. Content is prepared for review before publication.'], ['Is everything white-labelled?', 'Sitewell dashboards and reporting currently use Sitewell branding. Fully agency-branded dashboards and custom domains aren’t included. You still keep your client relationship and set your own pricing.'], ['What does it cost, and can I start with one website?', 'Yes, you can start with one website. Book a quick chat and we’ll agree the agency price, work included and any commitment before you start.']] as [$question, $answer])
                    <details class="py-5"><summary class="cursor-pointer font-medium focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">{{ $question }}</summary><p class="mt-4 text-base/7 text-ink/70">{{ $answer }}</p></details>
                @endforeach
            </div>
        </div>
    </section>
    <section id="join-beta" class="scroll-mt-8 border-t border-ink/10 py-14 sm:py-20">
        <div class="mx-auto max-w-6xl px-5 sm:px-8 lg:px-10">
            <h2 class="text-balance text-3xl font-medium tracking-tight sm:text-4xl">Fancy offering SEO to your clients?</h2>
            <p class="mt-5 max-w-xl text-lg/8 text-ink/70">Let’s have a quick chat about your agency, your clients and how Sitewell could work for you.</p>
            <a data-audit-book-call href="{{ route('marketing.ppc.book') }}" class="mt-7 inline-flex min-h-12 items-center rounded-full bg-garden px-6 py-3 font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">Book a quick chat <span aria-hidden="true" class="ml-3">↗</span></a>
            <details class="mt-10 border-t border-ink/10 pt-5">
                <summary class="cursor-pointer text-sm text-ink/60 focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden">More about SEO for agencies</summary>
                <nav aria-label="Agency resources" class="mt-5"><ul class="grid gap-3 sm:grid-cols-2">@foreach ($pages as $slug => $resource)<li><a href="{{ route('marketing.agencies.show', $slug) }}" class="text-sm text-ink/70 underline decoration-ink/20 underline-offset-4 hover:text-garden">{{ $resource['label'] }}</a></li>@endforeach</ul></nav>
            </details>
        </div>
    </section>
    @if (old('agency') !== null || $errors->has('agency') || session('agency_status'))
        @include('marketing.agencies.form', ['enquirySectionId' => 'agency-enquiry'])
    @endif
    @if ($turnstileEnabled)
        <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
    @endif
@endsection
