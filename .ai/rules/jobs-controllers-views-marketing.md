---
paths:
  - 'app/{Jobs/GenerateWebsiteAudit.php,Services/MarketingAuditScreenshot.php,Http/Controllers/FreeSiteAuditController.php},resources/views/marketing/website-audit.blade.php'
---

# Jobs Controllers Views Marketing

## Keep audit website previews small and private
Capture an optional 1024×640 compressed JPEG with Browsershot during the queued audit, after validating a public host and blocking browser redirects. Store previews on the private local disk, serve them only through the audit's expiring public-ID route, and do not let capture failure fail the audit. Keep the preview small beside the report title and hide it when unavailable.
