<x-mail::message>
# Your personal website review for {{ $audit->domain }}

@if (data_get($audit->personal_review, 'loom_url'))
I’ve recorded a personal video review of {{ $audit->domain }}, walking through what I’d improve and where I’d start.

@if (data_get($audit->personal_review, 'thumbnail_url'))
<a href="{{ data_get($audit->personal_review, 'loom_url') }}" style="display:block;text-decoration:none;">
<img src="{{ data_get($audit->personal_review, 'thumbnail_url') }}" alt="Watch the personal website review recorded for {{ $audit->domain }}" width="540" style="display:block;width:100%;max-width:540px;height:auto;border:0;border-radius:8px;">
</a>
@endif

<x-mail::button :url="data_get($audit->personal_review, 'loom_url')">
Watch your website review
</x-mail::button>

@else
I’ve reviewed your audit. Here are the three improvements I’d start with.

@foreach ($audit->personal_review as $priority)
## {{ $loop->iteration }}. {{ $priority['title'] }}

**Why it matters:** {{ $priority['impact'] }}

**Next step:** {{ $priority['next_step'] }}

@endforeach

@endif

What do you most want your website to bring you — enquiries, bookings or sales? Reply to this email and let me know.

[View your report]({{ $reportUrl }}) (available until {{ $audit->expires_at->format('j F Y') }}).

## Want me to take care of this?

If you’d like us to handle these improvements, we can agree the first steps on a short call. Sitewell can then look after your website and keep working on its search visibility at the level of support you choose.

<x-mail::button :url="$bookingUrl" color="secondary">
Book a call with Ross
</x-mail::button>

Ross at Sitewell

@if ($audit->marketing_consent_at)
You opted into ongoing website advice. [Unsubscribe]({{ $preferencesUrl }}) at any time.
@endif
</x-mail::message>
