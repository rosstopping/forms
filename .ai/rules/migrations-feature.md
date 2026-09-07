---
paths:
  - 'app/{Actions/ActivateWebsiteAuditTrial.php,Models/{WebsiteAudit.php,WebsiteDomain.php},Http/Controllers/WebsiteAuditOnboardingController.php},database/migrations/**/*website*claim*.php,tests/Feature/**/*WebsiteAudit*.php'
---

# Migrations Feature

## Verify website ownership before reserving domains
Get started may review any public URL, but email confirmation does not prove website ownership. Keep onboarding attempts unverified and non-exclusive until ownership is proven through Search Console, DNS, or the Sitewell Pixel. Only verified website domains are canonical/unique; a conflict must use an access or ownership-resolution flow and must never expose or attach another account’s data.
