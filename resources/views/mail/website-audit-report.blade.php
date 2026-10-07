<x-mail::message>
# Your search audit is ready

The automated audit has checked {{ $audit->domain }} and outlined the website fixes and search opportunities we found.

@if ($audit->personal_review_requested_at)
I’ll personally review your results and email you a video walking through what I’d improve.
@endif

<x-mail::button :url="$reportUrl">
View your report
</x-mail::button>

This private link is available until {{ $audit->expires_at->format('j F Y') }}.

<x-mail::panel>
## Talk through the next steps

Want help turning the report into more customers? Book a call with Ross to discuss what we’d do first.
</x-mail::panel>

<x-mail::button :url="$bookingUrl" color="secondary">
Book a call with Ross
</x-mail::button>

Ross at Sitewell

@if ($audit->marketing_consent_at)
You opted into ongoing website advice. [Unsubscribe]({{ $preferencesUrl }}) at any time.
@endif
</x-mail::message>
