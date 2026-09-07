---
paths:
  - 'app/{Http/Controllers/FreeSiteAuditController.php,Http/Requests/StoreFreeSiteAuditRequest.php,Jobs/GenerateWebsiteAudit.php,Models/WebsiteAudit.php,Services/MarketingTurnstileVerifier.php},resources/views/marketing/{free-site-audit,website-audit}.blade.php,routes/web.php'
---

# Views Marketing

## Keep onboarding audits anonymous until email claim
Free audit and Get started begin with only a website URL. Persist the queued result as an expiring WebsiteAudit, not a Prospect or User; do not collect personal details or send audit email before the later explicit email-claim step. Keep public submissions Turnstile-protected when configured, honeypot-protected, rate-limited by IP/domain, and SSRF-checked by the analyzer.
