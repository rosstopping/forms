---
paths:
  - 'app/Services/MarketingAudit*.php,resources/views/marketing/website-audit.blade.php'
  - 'app/Services/MarketingAuditResearch.php,resources/views/marketing/website-audit.blade.php'
---

# Services Views Marketing

## Count sitemap URLs and surface host mismatches
Show the total URLs listed by accessible sitemaps, with '+' only when fetching or count limits make the count partial. Track URLs on another host separately and warn plainly; a live sitemap can contain staging-domain URLs. Public search estimates may reuse matching-market provider data from a recent SeoSnapshot or completed public audit when live lookup is unavailable, but reject snapshots older than 30 days and show the measurement date.

## Prioritise useful public-audit rankings
Public audit keyword samples fetch page-one positions 1–10 and striking-distance positions 11–30 separately (up to 10 terms each); only request positions 1–100 as a fallback when both are empty. Show page-one and striking-distance rankings side by side, ordered by best position, and show other rankings only when neither group exists. Reuse saved matching-market snapshots and seven-day cached provider results; rendering must not call paid APIs. This supersedes the earlier one 20-term ranked-keyword request rule.
