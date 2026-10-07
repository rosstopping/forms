<x-mail::message>
@if ($audit->personal_review_requested_at)
# Thanks for sending me your website

I’ve received your request for {{ $audit->domain }}. I’ll take a closer look and email you a personal video within one working day, showing where I’d start to bring you more visitors and customers.

Is there a particular service or product you’d like more customers for? Reply to this email and let me know.

You can [revisit your website snapshot]({{ $reportUrl }}) until {{ $audit->expires_at->format('j F Y') }}.
@else
# Your search opportunity review

We’ve checked {{ $audit->domain }}. The next step is to identify which searches could bring you more relevant visitors and customers.

<x-mail::button :url="$reportUrl">
View your search snapshot
</x-mail::button>

This private link is available until {{ $audit->expires_at->format('j F Y') }}.
@endif

Prefer to talk it through? [Book a call with me]({{ $bookingUrl }}).

Ross at Sitewell

@if ($audit->marketing_consent_at)
You opted into ongoing website advice. [Unsubscribe]({{ $preferencesUrl }}) at any time.
@endif
</x-mail::message>
