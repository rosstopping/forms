@extends('layouts.marketing')

@section('title', 'Website care for growing businesses')
@section('meta_description', 'Sitewell keeps your website healthy, captures every lead, and helps you make the improvements that grow your business.')

@section('content')
    <section class="py-16 sm:py-24 lg:py-28">
        <div class="mx-auto grid max-w-7xl items-center gap-12 px-5 sm:px-8 lg:grid-cols-[9fr_11fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Website care, made visible</p>
                <h1 class="mt-5 max-w-[16ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl lg:text-7xl">Your website, well looked after</h1>
                <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Sitewell keeps your website healthy, captures every lead, and makes the next useful improvement clear—so your business stays visible, dependable, and ready to grow.</p>
                <div class="mt-8 flex flex-wrap items-center gap-5">
                    <a href="{{ route('marketing.contact') }}" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Get started</a>
                    <a href="{{ route('marketing.features') }}" class="text-base font-medium text-ink underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">See how it works →</a>
                </div>
            </div>
            <x-marketing.product-preview />
        </div>
    </section>

    <section class="bg-ink py-16 text-paper sm:py-20">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-apricot">See Sitewell in action</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A closer look at calmer website care</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-paper/65 sm:text-base">Watch how Sitewell brings your website health, leads, and next growth opportunities into one clear place.</p>
            </div>

            <div class="mt-10 overflow-hidden rounded-[min(1vw,var(--radius-xl))] bg-paper shadow-2xl shadow-black/30 ring-1 ring-white/15 sm:mt-12">
                <iframe
                    src="https://www.loom.com/embed/d406218f4a2843f7a7d8abbf804f2ba6"
                    title="See how Sitewell looks after your website"
                    class="aspect-[2000/1299] w-full bg-black"
                    loading="lazy"
                    allow="fullscreen"
                    allowfullscreen
                ></iframe>

                <a href="{{ route('marketing.contact') }}" class="group flex flex-col gap-6 border-t border-ink/10 bg-paper p-6 text-ink hover:bg-lichen focus-visible:outline-offset-4 sm:flex-row sm:items-center sm:justify-between sm:p-8">
                    <div class="min-w-0">
                        <p class="font-mono text-sm uppercase tracking-wide text-garden">Your website could be next</p>
                        <p class="mt-3 max-w-[40ch] font-display text-2xl font-semibold tracking-tight text-balance sm:text-3xl">Let’s make your website work harder for your business</p>
                        <p class="mt-3 max-w-[56ch] text-pretty text-base text-ink/65 sm:text-sm">Tell us what you need and we will help you find the right next step.</p>
                    </div>
                    <div class="flex shrink-0 items-center gap-3 font-medium">
                        <p class="text-base underline decoration-ink/20 underline-offset-4 group-hover:decoration-ink sm:text-sm">Talk to us</p>
                        <span class="grid size-12 shrink-0 place-items-center rounded-full bg-garden text-paper group-hover:bg-moss" aria-hidden="true">→</span>
                    </div>
                </a>
            </div>
        </div>
    </section>

    <section class="border-y border-ink/10">
        <div class="mx-auto grid max-w-7xl px-5 sm:grid-cols-2 sm:px-8 lg:grid-cols-4 lg:px-10">
            @foreach ([['Free website included', 'Need a new website? It is included with every plan.'], ['Healthier website', 'Health and SEO issues spotted before they cost you business.'], ['Every lead captured', 'No enquiry missed, every time.'], ['Clear growth opportunities', 'Striking-distance keywords and practical content recommendations.']] as [$title, $copy])
                <div class="border-t border-ink/10 py-7 first:border-t-0 sm:border-t-0 sm:px-6 sm:[&:nth-child(2n)]:border-l sm:[&:nth-child(2n)]:border-ink/10 sm:[&:nth-child(odd)]:pl-0 lg:[&:not(:first-child)]:border-l lg:[&:not(:first-child)]:border-ink/10 lg:[&:nth-child(odd)]:pl-6 lg:first:pr-6 lg:last:pr-0">
                    <p class="text-base font-medium sm:text-sm">{{ $title }}</p>
                    <p class="mt-2 text-base text-ink/55 sm:text-sm">{{ $copy }}</p>
                </div>
            @endforeach
        </div>
    </section>

    <section class="py-20 sm:py-28">
        <div class="mx-auto max-w-7xl px-5 sm:px-8 lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">One dependable rhythm</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">Capture, care for, improve</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">Everything your business needs to make its website a more dependable part of how you grow.</p>
            </div>
            <dl class="mt-14 grid gap-10 lg:grid-cols-3">
                @foreach ([['01', 'Capture every enquiry', 'Forms, spam protection, automatic acknowledgements, and a clear CRM make sure every potential customer gets the attention they deserve.'], ['02', 'Know what needs attention', 'Website health and SEO audits turn technical problems into plain next steps, with weekly reports that keep you informed.'], ['03', 'Improve with confidence', 'Search data reveals striking-distance keywords, content gaps, and clear recommendations for improving the pages you already have.']] as [$number, $title, $copy])
                    <div class="border-t border-ink/15 pt-6">
                        <dt><p class="font-mono text-base text-garden sm:text-sm">{{ $number }}</p><p class="mt-6 font-display text-2xl font-semibold tracking-tight">{{ $title }}</p></dt>
                        <dd class="mt-4 max-w-[56ch] text-pretty text-base text-ink/60 sm:text-sm">{{ $copy }}</dd>
                    </div>
                @endforeach
            </dl>
        </div>
    </section>

    <section class="bg-lichen/55 py-20 sm:py-28">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-2 lg:items-center lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Everything in one place</p>
                <h2 class="mt-4 max-w-[24ch] font-display text-4xl font-semibold tracking-tight text-balance sm:text-5xl">A calm control room for your business website</h2>
                <p class="mt-5 max-w-[48ch] text-pretty text-lg text-ink/60 sm:text-base">You do not need to become technical to look after your website. Sitewell brings the checks, leads, reports, and improvement work into one clear workspace.</p>
                <a href="{{ route('marketing.features') }}" class="mt-7 inline-flex text-base font-medium underline decoration-ink/20 underline-offset-4 hover:decoration-ink sm:text-sm">Explore every feature →</a>
            </div>
            <div class="grid gap-4">
                @foreach ([['Forms & CRM', 'Submissions, spam review, automatic replies, and follow-ups.'], ['Health & SEO audits', 'Scheduled checks, clear findings, and practical recommendations.'], ['Search growth', 'Striking-distance keywords, page-level opportunities, and content recommendations.'], ['Local presence', 'Google Business Profile checks, posts, and review replies on the Complete plan.']] as [$title, $copy])
                    <div class="grid grid-cols-[7rem_1fr] gap-4 border-t border-ink/15 pt-4"><p class="font-mono text-base text-garden sm:text-sm">{{ $title }}</p><p class="text-pretty text-base text-ink/65 sm:text-sm">{{ $copy }}</p></div>
                @endforeach
            </div>
        </div>
    </section>

    <x-marketing.cta />
@endsection
