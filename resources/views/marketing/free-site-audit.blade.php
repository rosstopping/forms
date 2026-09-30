@extends('layouts.marketing')

@section('title', 'Get your free search audit')
@section('meta_description', 'Enter your website address for a free search audit from Sitewell.')

@section('content')
<section class="px-3 pt-1 pb-16 sm:px-6 sm:pt-2 sm:pb-24" aria-labelledby="audit-title">
    <div class="mx-auto grid max-w-7xl justify-items-center gap-8 rounded-3xl bg-lichen px-5 py-16 text-center sm:gap-10 sm:px-10 sm:py-24">
        <div class="grid justify-items-center gap-5">
            <h1 id="audit-title" class="max-w-[18ch] text-4xl font-medium tracking-tight text-balance sm:text-6xl">See what your website needs.</h1>
            <p class="max-w-[48ch] text-pretty text-lg text-ink/65">Enter your website for a short report on what’s working and what needs attention.</p>
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
        <dl class="grid w-full max-w-4xl gap-6 border-t border-ink/10 pt-8 text-left sm:grid-cols-3 sm:gap-8 sm:pt-10">
            <div class="grid content-start gap-2">
                <dt class="font-medium text-ink">Search setup</dt>
                <dd class="text-pretty text-base text-ink/65 sm:text-sm">Page titles, headings, sitemap and crawl access.</dd>
            </div>
            <div class="grid content-start gap-2">
                <dt class="font-medium text-ink">Website health</dt>
                <dd class="text-pretty text-base text-ink/65 sm:text-sm">Homepage response, mobile setup and image text.</dd>
            </div>
            <div class="grid content-start gap-2">
                <dt class="font-medium text-ink">Security basics</dt>
                <dd class="text-pretty text-base text-ink/65 sm:text-sm">HTTPS and browser security headers.</dd>
            </div>
        </dl>
    </div>
</section>
@if ($turnstileEnabled)
    <script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer></script>
@endif
@endsection
