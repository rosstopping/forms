@extends('layouts.marketing')

@section('title', 'Free website and search audit')
@section('meta_description', 'Enter your website for a free Sitewell audit of website health, Google visibility and the work we would prioritise. No account needed.')

@section('content')
<section class="px-3 pt-1 pb-16 sm:px-6 sm:pt-2 sm:pb-24" aria-labelledby="audit-title">
    <div class="mx-auto grid max-w-7xl justify-items-center gap-8 rounded-3xl bg-lichen px-5 py-16 text-center sm:gap-10 sm:px-10 sm:py-24">
        <div class="grid justify-items-center gap-5">
            <h1 id="audit-title" class="max-w-3xl text-4xl font-medium tracking-tight text-balance sm:text-6xl">Enter your website and we'll tell you what to do next.</h1>
            <p class="max-w-[48ch] text-pretty text-lg text-ink/65">Enter your website to see where it stands and what we’d do to help more customers find you.</p>
        </div>
        <form method="POST" action="{{ route('marketing.free-site-audit.store') }}" data-audit-form data-marketing-attribution="{{ json_encode($attribution) }}" class="grid w-full max-w-xl gap-3 text-left">
            @csrf
            <div class="grid grid-cols-[minmax(0,1fr)_auto] items-center rounded-full bg-white p-1.5 ring-1 ring-black/15 focus-within:ring-2 focus-within:ring-garden">
                <label for="website_url" class="sr-only">Website address</label>
                <input id="website_url" name="website_url" type="text" required autofocus maxlength="255" inputmode="url" autocomplete="url" autocapitalize="none" spellcheck="false" placeholder="example.com" value="{{ old('website_url') }}" aria-invalid="{{ $errors->has('website_url') ? 'true' : 'false' }}" @error('website_url') aria-describedby="website-url-error" @enderror class="min-h-14 w-full min-w-0 rounded-full border-0 bg-transparent px-3 py-4 text-base text-ink placeholder:text-ink/50 focus:outline-none sm:px-5">
                <button type="submit" aria-label="Get your free search audit" class="inline-flex min-h-14 items-center justify-center gap-3 rounded-full bg-garden py-4 pr-4 pl-5 font-medium text-white hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden"><span class="sm:hidden">Free audit</span><span class="max-sm:hidden">Get your free search audit</span><span class="shrink-0" aria-hidden="true">→</span></button>
            </div>
            @error('website_url')<p id="website-url-error" role="alert" class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
            <div class="absolute left-[-9999px] size-px overflow-hidden" aria-hidden="true">
                <label for="_sitewell_check">Leave this field empty</label>
                <input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off">
            </div>
            @if ($turnstileEnabled)
                <div class="cf-turnstile justify-self-center" data-sitekey="{{ $turnstileSiteKey }}" data-theme="light" data-size="flexible"></div>
                @error('cf-turnstile-response')<p role="alert" class="text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
            @endif
        </form>
    </div>
</section>

<section class="pb-16 sm:pb-24" aria-labelledby="audit-preview-title">
    <div class="mx-auto max-w-7xl px-5 sm:px-10">
        <h2 id="audit-preview-title" class="max-w-[40ch] text-3xl font-medium tracking-tight text-balance text-ink sm:text-4xl">What your audit shows.</h2>
        <dl class="grid gap-5 pt-7 md:grid-cols-2 sm:pt-9">
            <div data-audit-preview class="grid min-w-0 overflow-hidden rounded-3xl bg-white ring-1 ring-ink/10">
                <div aria-hidden="true" class="grid min-h-44 content-center gap-3 bg-lichen px-6 py-6 sm:px-8">
                    <div class="flex max-w-sm items-center gap-3 rounded-full bg-white px-4 py-3 ring-1 ring-ink/10">
                        <span class="size-3 shrink-0 rounded-full border-2 border-garden"></span>
                        <span class="text-sm text-ink/55">Google searches</span>
                        <span class="ml-auto text-base text-garden">↗</span>
                    </div>
                    <div class="grid max-w-sm gap-2 pl-3">
                        <div class="flex items-center gap-3 rounded-xl bg-white px-4 py-3 ring-1 ring-ink/10"><span class="size-2 shrink-0 rounded-full bg-garden"></span><span class="h-2 w-2/3 rounded-full bg-ink/15"></span><span class="ml-auto h-2 w-8 rounded-full bg-garden/25"></span></div>
                        <div class="flex items-center gap-3 rounded-xl bg-white/65 px-4 py-3"><span class="size-2 shrink-0 rounded-full bg-ink/20"></span><span class="h-2 w-1/2 rounded-full bg-ink/10"></span><span class="ml-auto h-2 w-8 rounded-full bg-ink/10"></span></div>
                    </div>
                </div>
                <div class="grid content-start gap-2 p-6 sm:p-8">
                    <dt class="text-xl font-medium tracking-tight text-ink">Where you show up.</dt>
                    <dd class="max-w-[48ch] text-pretty text-base text-ink/65">Your Google rankings and the sites appearing alongside you.</dd>
                </div>
            </div>
            <div data-audit-preview class="grid min-w-0 overflow-hidden rounded-3xl bg-white ring-1 ring-ink/10">
                <div aria-hidden="true" class="grid min-h-44 place-items-center bg-lichen px-6 py-6 sm:px-8">
                    <div class="flex w-full max-w-sm items-center gap-5 rounded-2xl bg-white p-5 ring-1 ring-ink/10">
                        <div class="grid size-20 shrink-0 place-items-center rounded-full border-[10px] border-garden/15 border-r-garden border-b-garden"><span class="size-6 rounded-full bg-garden/15"></span></div>
                        <div class="grid min-w-0 flex-1 gap-3"><span class="text-sm font-medium text-ink/65">Website health</span><span class="h-2 w-full rounded-full bg-ink/10"></span><span class="h-2 w-3/4 rounded-full bg-garden/30"></span><span class="h-2 w-1/2 rounded-full bg-ink/10"></span></div>
                    </div>
                </div>
                <div class="grid content-start gap-2 p-6 sm:p-8">
                    <dt class="text-xl font-medium tracking-tight text-ink">What needs fixing.</dt>
                    <dd class="max-w-[48ch] text-pretty text-base text-ink/65">Pages found, technical health, and fixes to tackle first.</dd>
                </div>
            </div>
            <div data-audit-preview class="grid min-w-0 overflow-hidden rounded-3xl bg-white ring-1 ring-ink/10">
                <div aria-hidden="true" class="grid min-h-44 place-items-center bg-lichen px-6 py-6 sm:px-8">
                    <div class="grid w-full max-w-sm gap-3">
                        <div class="flex w-4/5 items-center gap-3 rounded-2xl rounded-bl-sm bg-white px-4 py-3 ring-1 ring-ink/10"><span class="font-medium text-garden">AI</span><span class="h-2 w-1/2 rounded-full bg-ink/15"></span></div>
                        <div class="grid gap-3 justify-self-end w-5/6 rounded-2xl rounded-br-sm bg-white p-4 ring-1 ring-ink/10"><span class="h-2 w-full rounded-full bg-ink/15"></span><span class="h-2 w-3/4 rounded-full bg-ink/10"></span><span class="flex items-center gap-2 text-sm font-medium text-garden"><span class="size-2 rounded-full bg-garden"></span> Sample answer</span></div>
                    </div>
                </div>
                <div class="grid content-start gap-2 p-6 sm:p-8">
                    <dt class="text-xl font-medium tracking-tight text-ink">Whether AI mentions you.</dt>
                    <dd class="max-w-[48ch] text-pretty text-base text-ink/65">Mentions and citations in sample answers to relevant questions, when found.</dd>
                </div>
            </div>
            <div data-audit-preview class="grid min-w-0 overflow-hidden rounded-3xl bg-white ring-1 ring-ink/10">
                <div aria-hidden="true" class="grid min-h-44 place-items-center bg-lichen px-6 py-6 sm:px-8">
                    <div class="grid w-full max-w-sm gap-4 rounded-2xl bg-white p-5 ring-1 ring-ink/10">
                        <div class="flex items-center gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-garden text-sm text-white">1</span><span class="text-sm font-medium text-ink/75">Fix</span><span class="ml-auto h-2 w-1/3 rounded-full bg-ink/10"></span></div>
                        <div class="flex items-center gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-garden/15 text-sm text-garden">2</span><span class="text-sm font-medium text-ink/75">Improve</span><span class="ml-auto h-2 w-1/2 rounded-full bg-ink/10"></span></div>
                        <div class="flex items-center gap-3"><span class="grid size-6 shrink-0 place-items-center rounded-full bg-garden/15 text-sm text-garden">3</span><span class="text-sm font-medium text-ink/75">Build</span><span class="ml-auto h-2 w-1/4 rounded-full bg-ink/10"></span></div>
                    </div>
                </div>
                <div class="grid content-start gap-2 p-6 sm:p-8">
                    <dt class="text-xl font-medium tracking-tight text-ink">Where to go next.</dt>
                    <dd class="max-w-[48ch] text-pretty text-base text-ink/65">Prioritised work and a six-month search scenario when there’s enough data.</dd>
                </div>
            </div>
        </dl>
    </div>
</section>
@if ($turnstileEnabled)
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
@endsection
