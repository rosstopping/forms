---
paths:
  - '{app/Models/MarketingConversion.php,app/Http/Controllers/FreeSiteAuditController.php,resources/js/marketing-events.js,resources/views/layouts/{marketing,ppc}.blade.php,resources/views/components/marketing/tag-manager.blade.php}'
---

# Views Components Marketing

## Count captured emails as Ads leads instead of audit submissions
October 8 decision supersedes audit_submitted as a lead conversion. Persist lead_captured only when a visitor first saves an email; record it inside the audit transaction with saved attribution, and flash its payload only to the capturing session. Do not expose email in tracking or replay lead events on report reads, refreshes, repeat submissions or admin requests. Reuse the installed GTM trigger sitewell_audit_submitted for this new stage with conversion_stage=email_captured; the legacy trigger name is compatibility only and must never fire on URL submission. Do not add a second Ads/GA4/server delivery. Both PPC and marketing layouts load the shared existing GTM container; confirmed call_booked remains a separate lead event.
