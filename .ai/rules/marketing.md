---
paths:
  - 'app/{Http/Controllers/FreeSiteAuditController.php,Http/Requests/StoreFreeSiteAuditRequest.php,Jobs/GenerateFreeSiteAudit.php,Mail/FreeSiteAuditResults.php},resources/views/{marketing/free-site-audit.blade.php,prospects/report.blade.php,mail/free-site-audit-results.blade.php}'
---

# Marketing

## Treat free audits as consented inbound leads
Free marketing audits create Prospect records owned by an admin, keep the submitted contact email, and run asynchronously through ProspectWebsiteAnalyzer. Email only the requester with a temporary signed report link; this transactional result email may send automatically, unlike cold prospect outreach. Keep public submissions throttled, honeypot-protected, consented, and SSRF-checked by the analyzer.
