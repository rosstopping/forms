---
paths:
  - 'app/Services/MarketingAudit*.php,resources/views/marketing/website-audit.blade.php'
---

# Services Views Marketing

## Count sitemap URLs and surface host mismatches
Show the total URLs listed by accessible sitemaps, with '+' only when fetching or count limits make the count partial. Track URLs on another host separately and warn plainly; a live sitemap can contain staging-domain URLs. Public search estimates may reuse matching-market provider data from a recent SeoSnapshot or completed public audit when live lookup is unavailable, but reject snapshots older than 30 days and show the measurement date.
