---
paths:
  - 'app/{Ai,Jobs,Services,Http/Controllers/Admin}/**/*Pixel*.php,resources/views/admin/{websites/**,website-health-reports/**}'
---

# Views Adminwebsites

## Keep Pixel remediation consolidated and review-first
Treat the Pixel tab as the operational source of truth for live changes, reviewable drafts, and recent deployment history. A report's single remediation action should launch every available delivery path (Pixel and/or GitHub). Content todos may create Pixel drafts only for safe title/meta changes on Pixel-seen, crawled pages; never auto-deploy them, and leave new pages or substantial body changes for GitHub.
