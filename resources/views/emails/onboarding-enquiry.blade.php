<x-mail::message>
# New Sitewell enquiry

**Name:** {{ $enquiry['name'] }}  
**Email:** {{ $enquiry['email'] }}  
**Agency:** {{ $enquiry['agency'] ?: 'Not provided' }}  
**Current website:** {{ $enquiry['website'] ?: 'Not provided' }}

## What they need

{{ $enquiry['goals'] }}

Reply directly to this email to continue the conversation.
</x-mail::message>
