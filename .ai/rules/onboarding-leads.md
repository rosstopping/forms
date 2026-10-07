---
paths:
  - 'app/{Models/WebsiteAudit*,Http/Controllers/{FreeSiteAuditController,WebsiteAuditEngagementController,PpcLandingController,CalWebhookController,Admin/OnboardingLeadController}.php},resources/{views/{marketing/website-audit,admin/onboarding-leads/index}.blade.php,js/{audit-engagement,audit-review,cal-booking}.js}'
---

# Onboarding Leads

## Keep the full audit controlled by Ross and record interest separately from conversions
Supersedes automatic full-report unlock on email capture. Visitor audit stays a qualitative search-opportunity snapshot before and after email, without exact rankings/scores/tables. Lead with relevant visitors/customers and Ross’s personal video within one working day; optional goal is enquiries/bookings/sales. Full data is admin-only or an admin-generated expiring signed link, never included automatically in visitor emails. Admin Onboarding shows first-party per-visit active-time/scroll estimates, popup, email-start/attempt/success and calendar click/open signals. Do not save unfinished email text or count clicks/telemetry as lead conversions. Successful email is server-recorded; booking is confirmed only through the signed Cal webhook with audit attribution. Track only the public snapshot, exclude admin views, keep CSRF/session/signature/expiry/rate limits and bound/idempotently merge client cumulative stats. Browser signals are approximate and may be blocked.
