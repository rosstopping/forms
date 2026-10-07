<x-mail::message>
# What I’d prioritise for {{ $audit->domain }}

I’ve reviewed your audit. Here are the three improvements I’d start with.

@foreach ($audit->personal_review as $priority)
## {{ $loop->iteration }}. {{ $priority['title'] }}

**Why it matters:** {{ $priority['impact'] }}

**Next step:** {{ $priority['next_step'] }}

@endforeach

What do you most want your website to bring you — enquiries, bookings or sales? Reply to this email and let me know.

[View your report]({{ $reportUrl }}) (available until {{ $audit->expires_at->format('j F Y') }}).

If you’d like help putting these improvements into practice, we can discuss what Sitewell could do for your business.

<x-mail::button :url="$bookingUrl" color="secondary">
Book a call with Ross
</x-mail::button>

Ross at Sitewell

@if ($audit->marketing_consent_at)
You opted into ongoing website advice. [Unsubscribe]({{ $preferencesUrl }}) at any time.
@endif
</x-mail::message>
