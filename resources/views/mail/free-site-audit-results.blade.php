<x-mail::message>
# Your website audit is ready

Hi {{ $prospect->contact_name }},

We’ve finished reviewing {{ $prospect->business_name }} across website availability, speed, search essentials, accessibility, structured data, security, and discoverability.

<x-mail::button :url="$reportUrl">
View your audit results
</x-mail::button>

The report shows what is working well and the practical issues worth reviewing first. It is based on publicly available information, so it is a useful starting point rather than a replacement for private analytics.

If you’d like help deciding what to tackle first, get in touch or book a quick call with Ross.

<x-mail::button :url="$contactUrl">
Get in touch
</x-mail::button>

<x-mail::button :url="$bookingUrl" color="success">
Book a call with Ross
</x-mail::button>

Thanks,<br>
Ross at Sitewell
</x-mail::message>
