---
paths:
  - 'app/{Models/User.php,Http/Controllers/Admin/OnboardingLeadController.php},resources/views/{admin/onboarding-leads/**,layouts/app.blade.php},routes/web.php,tests/Feature/OnboardingLeadTest.php'
---

# Onboarding Leads Feature

## Use claimed reviews as onboarding leads
The admin Onboarding workspace is the operational lead view for Get started signups. Include users through a claimed WebsiteAudit (not merely onboarding_status) so manually created accounts are excluded and repeat audits do not duplicate the user. Eager-load the latest claimed audit, website, domains, and Search Console connection; show trial, verification method, and call state with admin actions and filters.
