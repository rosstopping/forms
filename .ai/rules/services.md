---
paths:
  - app/Services/WebsiteCrawler.php
  - 'app/Services/Pixel*.php'
  - 'app/Services/{SitemapFetcher,ProspectWebsiteAnalyzer,WebsiteHealthAuditor}.php'
  - 'app/Services/WeeklyReport*.php'
  - app/Services/SearchConsoleClient.php
---

# Services

## Preserve discovered URL trailing slashes
URL normalization must preserve non-root trailing slashes so the crawler requests the exact sitemap/link target and can distinguish canonical pages from genuine redirecting links. Resolve relative links against a trailing-slash path as a directory.

## Version Pixel payload caches and hash observability data
Cache Pixel payload changes by website id, monotonic pixel_payload_version, and normalized URL hash; lifecycle or site-control changes must increment that version. Public-request observability must be throttled and log hashes/reasons only—never site keys, raw URLs, or optimisation content.

## Follow bounded same-host sitemap redirects
Public and website-health sitemap availability checks use SitemapFetcher and evaluate the final HTTP response. Follow at most five HTTP(S) redirects on the original host/port; resolve relative Location values, stop loops, and reject credential-bearing or cross-host targets. Keep homepage/page redirect reporting unchanged; do not globally enable redirects on audit requests.

## Append verified AI Visibility facts to the shared Weekly Overview
WeeklyReportBuilder includes AI Visibility only from persisted observations. WeeklyReportGenerator appends its computed AI summary verbatim and excludes that topic from the narrative writer, rejecting generated AI-platform claims. Reuse the frozen overview in the existing weekly email; do not generate visibility checks while building or rendering reports.

## Distinguish Search Console access loss from temporary failures
Persist property-specific permission failures for a visible site warning, including failures from background jobs. Google can also return 403 for quota limits; do not label these as lost verification. Clear the warning only after a successful read of the same selected property, never from listing sites or an old property's response. Search Console access loss is not proof that Sitewell website ownership should be revoked.
