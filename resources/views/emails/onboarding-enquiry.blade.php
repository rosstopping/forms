<x-mail::message>
# New Sitewell enquiry

**Name:** {{ $enquiry['name'] }}  
**Email:** {{ $enquiry['email'] }}  
**Agency:** {{ $enquiry['agency'] ?: 'Not provided' }}  
**Current website:** {{ $enquiry['website'] ?: 'Not provided' }}

@if (($enquiry['type'] ?? null) === 'agency_beta')
**Agency beta enquiry**

**Client websites:** {{ $enquiry['client_websites'] }}

**Currently offers SEO:** {{ $enquiry['offers_seo'] === 'yes' ? 'Yes' : 'No' }}
@endif

## What they need

{{ $enquiry['goals'] }}

Reply directly to this email to continue the conversation.
</x-mail::message>
