<x-mail::message>
# New onboarding enquiry

**Name:** {{ $enquiry['name'] }}  
**Email:** {{ $enquiry['email'] }}  
**Agency:** {{ $enquiry['agency'] ?: 'Not provided' }}  
**Number of websites:** {{ $enquiry['website_count'] }}

## What they need

{{ $enquiry['goals'] }}

Reply directly to this email to continue the conversation.
</x-mail::message>
