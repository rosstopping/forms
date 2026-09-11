---
paths:
  - 'app/{Http/Controllers/Admin/SearchConsoleController.php,Services/SearchConsolePropertyMatcher.php,Actions/VerifyWebsiteDomainFromSearchConsole.php},resources/views/admin/websites/search-console-property.blade.php'
---

# Controllers Admin Views Admin Websites

## Restrict Search Console to the configured domain
Only list and accept Google Search Console properties matching the website's configured primary domain. Enforce the match server-side before saving: sc-domain properties compare canonical domains (including www coverage), while URL-prefix properties require the exact configured host. A Google account with no match gets an empty state, never access to an unrelated property.
