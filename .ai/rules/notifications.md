---
paths:
  - 'resources/views/{emails/**,mail/**,vendor/mail/**,vendor/notifications/**,components/email-layout.blade.php}'
---

# Notifications

## Share Sitewell email branding without changing client replies
Sitewell emails share the table-based vendor/mail HTML layout and default inline CSS theme. Standalone HTML mail uses x-email-layout to inline the same theme, passing markdown=false to preserve saved text and line breaks. Notification sign-offs and plain-text headers/footers use Sitewell. New-lead submission notifications use the website name as the title/header, no Sitewell footer and mail@digizu.co.uk as the sender. Put enquiry data before technical details and retain customer Reply-To and signed spam links. Client-authored form acknowledgements stay unbranded with their original HTML/text and sender. Outreach branding must not introduce links beyond the existing tracked content (linked=false); preserve signed actions, viewer GitHub-link restrictions, and tracking pixels.
