<x-mail::message>
# Your search audit is ready

We’ve reviewed {{ $audit->domain }} and outlined the website fixes and search opportunities we found.

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
</x-mail::message>
