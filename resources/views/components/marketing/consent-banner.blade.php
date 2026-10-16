<section hidden data-consent-banner aria-label="Cookie preferences" class="fixed inset-x-0 bottom-0 z-50 max-h-[75dvh] overflow-y-auto border-t border-ink/15 bg-white p-5 shadow-xl sm:p-6">
    <div class="mx-auto grid max-w-7xl items-center gap-5 lg:grid-cols-[1fr_auto]">
        <div class="min-w-0">
            <h2 class="text-lg font-medium text-ink">Your cookie choice</h2>
            <p class="mt-2 max-w-[75ch] text-pretty text-base/7 text-ink/70 sm:text-sm/6">We use essential cookies to keep the site working. With your permission, Google Ads and Analytics can measure visits and enquiries and support advertising. Optional measurement stays off unless you accept. You can change your choice using Cookie settings in the footer; withdrawing consent reloads the page.</p>
            <a href="{{ route('marketing.privacy') }}" class="mt-2 inline-block text-base underline underline-offset-4 sm:text-sm">Privacy and cookies</a>
        </div>
        <div class="grid gap-3 sm:grid-cols-2">
            <button type="button" data-consent-accept class="min-h-12 rounded-full bg-white px-4 py-3 text-base font-medium text-ink ring-1 ring-ink/25 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden sm:text-sm">Accept optional cookies</button>
            <button type="button" data-consent-reject class="min-h-12 rounded-full bg-white px-4 py-3 text-base font-medium text-ink ring-1 ring-ink/25 hover:bg-lichen focus-visible:outline-2 focus-visible:outline-offset-4 focus-visible:outline-garden sm:text-sm">Reject optional cookies</button>
        </div>
    </div>
</section>
<noscript><p class="px-5 py-3 text-base text-ink/70 sm:text-sm">Optional Google measurement is off because JavaScript is disabled.</p></noscript>
