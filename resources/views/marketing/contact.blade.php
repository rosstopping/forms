@extends('layouts.marketing')

@section('title', 'Start onboarding')
@section('meta_description', 'Tell Sitewell about your websites and start a calm, guided onboarding process.')

@section('content')
    <section class="py-16 sm:py-24">
        <div class="mx-auto grid max-w-7xl gap-12 px-5 sm:px-8 lg:grid-cols-[2fr_3fr] lg:px-10">
            <div>
                <p class="font-mono text-sm uppercase tracking-wide text-garden">Start onboarding</p>
                <h1 class="mt-5 max-w-[20ch] font-display text-5xl font-semibold tracking-tight text-balance sm:text-6xl">A clean handover starts here</h1>
                <p class="mt-6 max-w-[48ch] text-pretty text-lg text-ink/65 sm:text-base">Tell us about the websites you look after and where the current process feels fragile. We’ll come back with a practical onboarding plan.</p>
                <dl class="mt-12 grid gap-8">
                    @foreach ([['01', 'We map the portfolio', 'Sites, ownership, forms, repositories, and reporting expectations.'], ['02', 'We connect the signals', 'Search Console, form delivery, health checks, and the people who need access.'], ['03', 'You stay in control', 'A clear workspace, reviewable changes, and support when your team needs it.']] as [$number, $title, $copy])
                        <div class="grid grid-cols-[3rem_1fr] gap-4 border-t border-ink/15 pt-5"><dt class="font-mono text-base text-garden sm:text-sm">{{ $number }}</dt><dd><p class="text-base font-medium sm:text-sm">{{ $title }}</p><p class="mt-2 text-pretty text-base text-ink/55 sm:text-sm">{{ $copy }}</p></dd></div>
                    @endforeach
                </dl>
            </div>

            <div class="rounded-[min(1.25vw,1rem)] bg-[#fffefa] p-6 shadow-xl shadow-ink/5 ring-1 ring-ink/10 sm:p-8">
                @if (session('status'))
                    <div class="mb-8 rounded-md bg-lichen px-4 py-3 text-base text-moss sm:text-sm" role="status">{{ session('status') }}</div>
                @endif
                <form method="POST" action="https://digizuforms.on-forge.com/submit">
                    <input type="hidden" name="_form_name" value="Contact form">

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
                        <div><label for="agency" class="text-base font-medium sm:text-sm">Agency or business</label><input id="agency" name="agency" type="text" value="{{ old('agency') }}" autocomplete="organization" @class(['mt-2 w-full rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('agency')])>@error('agency')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    </div>
                    <div><label for="email" class="text-base font-medium sm:text-sm">Work email</label><input id="email" name="email" type="email" value="{{ old('email') }}" autocomplete="email" required @class(['mt-2 w-full rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('email')])>@error('email')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    <div>
                        <label for="website_count" class="text-base font-medium sm:text-sm">How many websites do you look after?</label>
                        <div class="mt-2 inline-grid w-full grid-cols-[1fr_--spacing(8)]">
                            <select id="website_count" name="website_count" required @class(['col-span-full row-start-1 w-full appearance-none rounded-md bg-white px-3.5 py-3 pr-8 text-base shadow-sm ring-1 ring-ink/10 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('website_count')])>
                                <option value="">Select a range</option>
                                @foreach (['1' => '1 website', '2-5' => '2–5 websites', '6-15' => '6–15 websites', '16-40' => '16–40 websites', '40+' => 'More than 40 websites'] as $value => $label)<option value="{{ $value }}" @selected(old('website_count', request('plan') === 'foundation' ? '1' : (request('plan') === 'studio' ? '6-15' : (request('plan') === 'partner' ? '16-40' : ''))) === $value)>{{ $label }}</option>@endforeach
                            </select>
                            <svg viewBox="0 0 8 5" width="8" height="5" fill="none" class="pointer-events-none col-start-2 row-start-1 place-self-center stroke-ink/50" aria-hidden="true"><path d="M.5.5 4 4 7.5.5" stroke-width="1.25" /></svg>
                        </div>
                        @error('website_count')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror
                    </div>
                    <div><label for="goals" class="text-base font-medium sm:text-sm">What would you like to improve?</label><textarea id="goals" name="goals" rows="6" required placeholder="Tell us about your current setup, the sites involved, and what would make life easier." @class(['mt-2 w-full resize-y rounded-md bg-white px-3.5 py-3 text-base shadow-sm ring-1 ring-ink/10 placeholder:text-ink/35 hover:ring-ink/20 focus:outline-2 focus:-outline-offset-1 focus:outline-garden sm:py-2.5 sm:text-sm', 'ring-red-600' => $errors->has('goals')])>{{ old('goals') }}</textarea>@error('goals')<p class="mt-2 text-base text-red-700 sm:text-sm">{{ $message }}</p>@enderror</div>
                    <div class="absolute -left-[9999rem]" aria-hidden="true"><label for="_sitewell_check">Leave this blank</label><input id="_sitewell_check" name="_sitewell_check" type="text" tabindex="-1" autocomplete="off"></div>
                    <div class="flex flex-wrap items-center justify-between gap-5"><p class="max-w-[48ch] text-pretty text-base text-ink/50 sm:text-sm">We’ll use these details only to respond to your onboarding request.</p><button type="submit" class="rounded-md bg-garden px-4 py-3 text-base font-medium text-white ring-1 ring-garden hover:bg-moss focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-garden sm:text-sm">Send onboarding request</button></div>
                </form>
            </div>
        </div>
    </section>
@endsection
