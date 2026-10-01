---
paths:
  - '{app/Http/Controllers/FreeSiteAuditController.php,app/Mail/WebsiteAudit*.php,resources/views/marketing/website-audit.blade.php}'
---

# Controllers Mail Views Marketing

## Audit report email requests stay anonymous
After a completed public website audit, visitors may request one emailed report link without creating an account. Store the normalized email and report_requested_at on the WebsiteAudit, extend expires_at to 14 days from the request, queue the visitor report and owner lead email after commit, and surface the audit in Admin Onboarding. The report email should include a call booking link for Ross; do not bring back SaaS signup copy.
