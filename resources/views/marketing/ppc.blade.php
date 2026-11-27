@extends('layouts.ppc')

@section('structured_data')
    @php
    $faqSchema = [
        '@context' => 'https://schema.org',
        '@type' => 'FAQPage',
        'mainEntity' => array_map(fn (array $faq): array => [
            '@type' => 'Question', 'name' => $faq[0],
            'acceptedAnswer' => ['@type' => 'Answer', 'text' => $faq[1]],
        ], $faqs),
    ];
    @endphp
    <script type="application/ld+json">@json($faqSchema, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT)</script>
@endsection

@section('content')
<section class="marketing-hero">
    <div class="mx-auto grid max-w-7xl gap-6 px-5 sm:px-8 lg:px-10">
        <h1 class="max-w-[24ch] text-4xl font-medium tracking-tight text-balance sm:text-5xl lg:text-6xl">{{ $landing['heading'] }}</h1>
        <p class="max-w-[56ch] text-lg text-ink/70">{{ $landing['intro'] }}</p>
        <x-marketing.ppc-actions primary />
        <p class="max-w-[64ch] text-base text-ink/65">The initial check uses public information. Website access is only needed for agreed changes.</p>
    </div>
</section>
<section class="py-12 sm:py-16">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-2 lg:gap-12 lg:px-10">
        <div class="grid content-start gap-5">
            <h2 class="max-w-[30ch] text-3xl font-medium tracking-tight sm:text-4xl">{{ $landing['problem_title'] }}</h2>
            <p class="max-w-[56ch] text-lg text-ink/70">{{ $landing['problem'] }}</p>
        </div>
        <div class="grid content-start gap-4 border-t border-ink/10 pt-6">
            <h3 class="text-2xl font-medium tracking-tight">An example of the work</h3>
            <p class="max-w-[56ch] text-base text-ink/70">{{ $landing['example'] }}</p>
            <p class="text-base text-ink/65 sm:text-sm">Illustrative example. This isn’t a measured client result.</p>
        </div>
    </div>
</section>
<section id="work" class="bg-lichen py-12 sm:py-16">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:px-10">
        <h2 class="max-w-[30ch] text-3xl font-medium tracking-tight sm:text-4xl">Website and SEO improvements</h2>
        <dl class="grid gap-8 md:grid-cols-3">
            @foreach ($landing['priorities'] as [$title, $copy])
                <div class="grid content-start gap-3 border-t border-ink/10 pt-6"><dt class="text-xl font-medium">{{ $title }}</dt><dd class="text-base text-ink/70">{{ $copy }}</dd></div>
            @endforeach
        </dl>
    </div>
</section>
<section class="py-12 sm:py-16">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-2 lg:gap-12 lg:px-10">
        <div class="grid content-start gap-5"><h2 class="max-w-[30ch] text-3xl font-medium tracking-tight sm:text-4xl">From your first check to ongoing work</h2><p class="max-w-[56ch] text-lg text-ink/70">Start with the free search audit. If you want help with the findings, we’ll agree the priorities, access and scope before making changes.</p></div>
        <ol role="list" class="grid gap-6">
            @foreach ([['Review your website', 'Check the available search and website evidence, then agree what needs attention.'], ['Make the agreed improvements', 'Work on content, service pages and technical issues around your website and plan.'], ['Report on progress', 'Receive weekly updates on completed work and available search measures, with the next priorities.']] as [$title, $copy])
                <li class="grid gap-2 border-t border-ink/10 pt-5"><h3 class="text-xl font-medium">{{ $title }}</h3><p class="text-base text-ink/70">{{ $copy }}</p></li>
            @endforeach
        </ol>
    </div>
</section>
<section id="ross" class="bg-lichen py-12 sm:py-16">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-2 lg:gap-12 lg:px-10">
        <div class="grid content-start gap-5"><h2 class="max-w-[30ch] text-3xl font-medium tracking-tight sm:text-4xl">Work directly with Ross</h2><p class="max-w-[56ch] text-lg text-ink/70">I’m a web developer based in Doncaster. Digizu brings together the checks, priorities and ongoing work involved in looking after your website and SEO.</p><p class="text-base text-ink/70">No long-term contract. We’ll explain the scope and access needed before you decide.</p></div>
        <div class="grid content-start gap-5"><h3 class="text-2xl font-medium tracking-tight">See how Digizu works</h3><p class="max-w-[56ch] text-base text-ink/70">The walkthrough shows how website work and progress are recorded.</p><a href="https://www.loom.com/share/d406218f4a2843f7a7d8abbf804f2ba6" target="_blank" rel="noopener noreferrer" class="justify-self-start text-garden underline underline-offset-4">Watch the Digizu walkthrough</a><a href="{{ route('marketing.ppc.book') }}" class="justify-self-start text-garden underline underline-offset-4">Book a call with Ross</a></div>
    </div>
</section>
<section class="py-12 sm:py-16">
    <div class="mx-auto grid max-w-7xl gap-8 px-5 sm:px-8 lg:grid-cols-2 lg:gap-12 lg:px-10">
        <h2 class="max-w-[30ch] text-3xl font-medium tracking-tight sm:text-4xl">Questions about the service</h2>
        <div class="divide-y divide-ink/15 border-y border-ink/15">
            @foreach ($faqs as [$question, $answer])
                <details class="group py-5"><summary class="cursor-pointer font-medium marker:text-garden">{{ $question }}</summary><p class="mt-4 max-w-[56ch] text-base text-ink/70">{{ $answer }}</p></details>
            @endforeach
        </div>
    </div>
</section>
<section class="mx-3 mb-12 rounded-3xl bg-lichen py-12 sm:mx-6 sm:py-16">
    <div class="mx-auto grid max-w-7xl gap-6 px-5 sm:px-8 lg:px-10"><h2 class="max-w-[30ch] text-3xl font-medium tracking-tight sm:text-4xl">Start with your website</h2><x-marketing.ppc-actions primary />
        <nav aria-label="Related SEO services" class="flex flex-wrap gap-x-6 gap-y-3 text-base text-ink/70">
            @foreach ($landing['related'] as $related)
                <a href="{{ route('marketing.ppc.'.$related) }}" class="underline underline-offset-4 hover:text-ink">{{ config('ppc.pages.'.$related.'.heading') }}</a>
            @endforeach
        </nav>
    </div>
</section>
@endsection
