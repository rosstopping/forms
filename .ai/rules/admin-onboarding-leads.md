---
paths:
  - '{app/Http/Controllers/FreeSiteAuditController.php,resources/views/admin/onboarding-leads/index.blade.php}'
---

# Admin Onboarding Leads

## Keep customer and full report access separate in Onboarding
Onboarding offers a read-only customer report link beside the CSRF-protected View full report action that explicitly starts deferred research. Signed-in global admins may view expired customer reports and their saved screenshots without extending visitor expiry; guests and ordinary users still receive 404 after expiry. Opening the customer view must never queue private research.
