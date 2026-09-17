        <section class="@container rounded-xl border border-slate-950/10 bg-white p-5 lg:col-span-2 sm:p-6" aria-labelledby="form-onboarding-title">
            <h2 id="form-onboarding-title" class="text-xl font-semibold text-slate-950">Connect a website form</h2>
            <p class="mt-1 text-base text-slate-600 sm:text-sm">Use the installation example when adding a new form to this website.</p>
            <details class="group mt-4 rounded-lg bg-slate-50 open:ring-1 open:ring-slate-950/10">
                <summary class="flex cursor-pointer list-none items-center justify-between gap-3 rounded-lg px-4 py-3 text-base font-medium text-slate-800 hover:bg-slate-100 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-teal-600 sm:text-sm [&::-webkit-details-marker]:hidden">
                    Show installation instructions
                    <svg viewBox="0 0 20 20" fill="none" stroke="currentColor" stroke-width="1.75" class="size-5 shrink-0 group-open:rotate-180 sm:size-4" aria-hidden="true"><path d="m5 7.5 5 5 5-5" stroke-linecap="round" stroke-linejoin="round"/></svg>
                </summary>
            <div class="grid gap-5 border-t border-slate-950/10 p-4 @4xl:grid-cols-[2fr_3fr] @4xl:gap-6">
                <div class="min-w-0">
                    <p class="mt-2 text-base text-pretty text-blue-900 sm:text-sm">Paste the example into the website, then replace or add the fields you need. Submissions from {{ $website->domains->firstWhere('is_primary', true)?->domain ?? $website->domains->first()?->domain ?? 'this website' }} will be matched automatically.</p>

                    <dl class="mt-5 grid gap-4">
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">1. Post to the shared endpoint</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Use <code class="font-mono">POST</code> and the action shown in the example. No API key, website ID, or CSRF field is required.</dd>
                        </div>
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">2. Give the form a stable name</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Set <code class="font-mono">_form_name</code> to a clear name such as “Contact form”. Keep it unchanged after launch; a different name may create a separate form.</dd>
                        </div>
                        <div>
                            <dt class="text-base font-semibold text-blue-950 sm:text-sm">3. Keep the honeypot empty</dt>
                            <dd class="mt-1 text-base text-pretty text-blue-900 sm:text-sm">Include the hidden <code class="font-mono">_honeypot</code> field. Visitors will not see it, while bots that fill it will be recorded as spam without triggering notifications.</dd>
                        </div>
                    </dl>
                </div>

                <div class="min-w-0 rounded-md bg-slate-950 p-3 [--padding:--spacing(3)] [--radius:var(--radius-md)]">
                    <div class="flex items-center justify-between gap-3 pb-3">
                        <p class="min-w-0 truncate font-mono text-sm text-slate-300">HTML · complete example</p>
                        <button type="button" class="js-copy-text relative shrink-0 rounded-md border border-white/15 bg-white/10 px-3 py-2 text-base font-medium text-white hover:bg-white/15 focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-white sm:text-sm" data-copy-target="form-onboarding-example" data-copy-label="Copy example" data-copied-label="Copied">Copy example<span class="absolute top-1/2 left-1/2 size-[max(100%,3rem)] -translate-1/2 pointer-fine:hidden" aria-hidden="true"></span></button>
                    </div>
                    <textarea id="form-onboarding-example" class="h-96 w-full resize-y rounded-[calc(var(--radius)-var(--padding))] border-0 bg-slate-900 p-3 font-mono text-sm text-slate-100 focus:outline-2 focus:outline-offset-2 focus:outline-blue-400" readonly spellcheck="false">&lt;form method="POST" action="{{ route('forms.submit') }}"&gt;
    &lt;input type="hidden" name="_form_name" value="Contact form"&gt;

    &lt;div
        style="position:absolute;left:-9999px;width:1px;height:1px;overflow:hidden"
        aria-hidden="true"
    &gt;
        &lt;label&gt;
            Leave this field empty
            &lt;input
                type="text"
                name="_honeypot"
                tabindex="-1"
                autocomplete="off"
            &gt;
        &lt;/label&gt;
    &lt;/div&gt;

    &lt;label&gt;
        Name
        &lt;input type="text" name="name" required&gt;
    &lt;/label&gt;

    &lt;label&gt;
        Email
        &lt;input type="email" name="email" required&gt;
    &lt;/label&gt;

    &lt;label&gt;
        Message
        &lt;textarea name="message" required&gt;&lt;/textarea&gt;
    &lt;/label&gt;

    &lt;!-- Replace YOUR_TURNSTILE_SITE_KEY with your Cloudflare public site key.
         Save the matching site and secret keys in Sitewell website settings
         and enable Turnstile protection. Never put the secret key here.
         The widget adds the cf-turnstile-response field automatically. --&gt;
    &lt;div class="cf-turnstile" data-sitekey="{{ $website->turnstile_site_key ?: 'YOUR_TURNSTILE_SITE_KEY' }}"&gt;&lt;/div&gt;

    &lt;button type="submit"&gt;Send enquiry&lt;/button&gt;
&lt;/form&gt;

&lt;!-- Include this script once per page. --&gt;
&lt;script src="https://challenges.cloudflare.com/turnstile/v0/api.js" async defer&gt;&lt;/script&gt;</textarea>
                </div>
            </div>
            </details>
        </section>
