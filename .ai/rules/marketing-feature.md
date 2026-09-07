---
paths:
  - '{app/Actions/StoreSitewellContactLead.php,app/Http/Controllers/OnboardingEnquiryController.php,resources/views/marketing/contact.blade.php,config/marketing.php,tests/Feature/MarketingPagesTest.php}'
---

# Marketing Feature

## Keep Contact separate from Get started
The public Contact page is a normal Sitewell enquiry form, not the website-only Get started flow. Store each valid enquiry as a new FormSubmission under the configured/detected Sitewell website so it appears in Leads, and also send the internal notification email. Keep honeypot validation and route throttling.
