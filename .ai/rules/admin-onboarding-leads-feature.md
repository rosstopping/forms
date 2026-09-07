---
paths:
  - 'app/{Http/Controllers/{CalWebhookController.php,Admin/OnboardingLeadController.php},Models/{User.php,WebsiteAudit.php}},resources/views/admin/onboarding-leads/**,routes/web.php,tests/Feature/{CalWebhookTest.php,OnboardingLeadTest.php}'
---

# Admin Onboarding Leads Feature

## Track the full Get Started funnel
The admin Onboarding workspace includes both claimed users and unclaimed WebsiteAudit submissions. Cal.com webhook events are authenticated with X-Cal-Signature-256 and matched to claimed onboarding users by attendee email; created/rescheduled bookings mark booked, cancellations clear booked, and meeting-ended marks completed.
