@extends('layouts.marketing')

@section('title', 'Contact Sitewell')
@section('meta_description', 'Talk to a Sitewell specialist about managing your website, SEO, enquiries, content, and local visibility.')

@section('content')
    <section class="marketing-hero">
        <div class="mx-auto grid max-w-7xl gap-10 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="text-base font-medium sm:text-sm text-garden">Contact Sitewell</p>
                <h1 class="mt-5 max-w-[20ch] font-sans text-4xl font-medium tracking-tight text-balance sm:text-6xl">Talk to Sitewell.</h1>
                <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Tell us about your business and your website. One of our specialists will assess what needs attention and explain how we can manage its website health, SEO, and enquiries for you.</p>
                <div class="mt-8 border-y border-ink/15 py-5">
                    <p class="text-base font-medium sm:text-sm text-garden">Prefer to talk?</p>
                    <a href="tel:+441302248374" class="group mt-3 inline-flex items-center gap-3 focus-visible:outline-offset-4">
                        <p class="font-sans text-3xl font-semibold tracking-tight tabular-nums underline decoration-ink/20 underline-offset-8 group-hover:decoration-ink sm:text-4xl">01302 248 374</p>
                        <span class="text-garden" aria-hidden="true">→</span>
                    </a>
                    <p class="mt-3 text-pretty text-base text-ink/55 sm:text-sm">Speak to Ross about your website.</p>
                </div>

            </div>

            <div class="rounded-3xl bg-lichen p-6 shadow-none shadow-ink/5 ring-1 ring-ink/10 sm:p-8">
                @if (session('status'))
                    <div class="mb-8 rounded-md bg-lichen px-4 py-3 text-base text-moss sm:text-sm" role="status">{{ session('status') }}</div>
                @endif
                <form method="POST" action="{{ route('marketing.contact.store') }}" class="grid grid-cols-1 gap-y-4">
                    @csrf

                    <div
                        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
                        aria-hidden="true"
                    >
                        <label>
                            Leave this field empty
                            <input
                                type="text"
                                name="_honeypot"
                                tabindex="-1"
                                autocomplete="off"
                            >
                        </label>
                    </div>
                    <div class="grid gap-6 sm:grid-cols-2">
                        <div><label for="name" class="text-base font-medium sm:text-sm">Your name</label><input id="name" name="name" type="text" value="{{ old('name') }}" autocomplete="name" required @class(['mt-2 w-full rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('name')])>@error('name')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                        <div><label for="agency" class="text-base font-medium sm:text-sm">Business name</label><input id="agency" name="agency" type="text" value="{{ old('agency') }}" autocomplete="organization" @class(['mt-2 w-full rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('agency')])>@error('agency')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    </div>
                    <div><label for="email" class="text-base font-medium sm:text-sm">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required @class(['mt-2 w-full rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('email')])>@error('email')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    <div><label for="website" class="text-base font-medium sm:text-sm">Current website <span class="font-normal text-ink/50">(if you have one)</span></label><input id="website" name="website" type="url" inputmode="url" value="{{ old('website') }}" autocomplete="url" placeholder="https://example.com" @class(['mt-2 w-full rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('website')])>@error('website')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    <div><label for="goals" class="text-base font-medium sm:text-sm">What would you like to improve?</label><textarea id="goals" name="goals" rows="6" required placeholder="Tell us about your website today and what you would like it to do better for your business." @class(['mt-2 w-full resize-y rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('goals')])>{{ old('goals') }}</textarea>@error('goals')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    <div class="absolute -left-[9999rem]" aria-hidden="true"><label for="_sitewell_check">Leave this blank</label><input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off"></div>
                    <div class="flex flex-wrap items-center justify-between gap-5"><p class="max-w-[48ch] text-pretty text-base text-ink/50 sm:text-sm">We’ll use these details only to respond to your request.</p><button type="submit" class="rounded-full bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Send enquiry</button></div>
                </form>
            </div>
        </div>
    </section>
@endsection
