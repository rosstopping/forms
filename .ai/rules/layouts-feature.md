---
paths:
  - 'routes/web.php,app/Http/Controllers/Admin/GoogleAdsController.php,resources/views/layouts/app.blade.php,tests/Feature/GoogleAdsConnectionTest.php'
---

# Layouts Feature

## Google Ads requires Complete membership
Google Ads is a Complete-plan website feature. Hide its desktop/mobile navigation unless the current website owner has an active Complete entitlement; global admins bypass for support. Gate every website-scoped Ads route with membership:complete, including read access and mutations. The OAuth callback has no website route parameter, so recheck the state website owner’s Complete entitlement before exchanging the code. Shared managers inherit the website owner’s entitlement, not their personal tier.
