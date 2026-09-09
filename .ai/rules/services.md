---
paths:
  - app/Services/WebsiteCrawler.php
  - 'app/Services/Pixel*.php'
  - 'app/Services/{SitemapFetcher,ProspectWebsiteAnalyzer,WebsiteHealthAuditor}.php'
---

# Services

## Preserve discovered URL trailing slashes
URL normalization must preserve non-root trailing slashes so the crawler requests the exact sitemap/link target and can distinguish canonical pages from genuine redirecting links. Resolve relative links against a trailing-slash path as a directory.

## Version Pixel payload caches and hash observability data
Cache Pixel payload changes by website id, monotonic pixel_payload_version, and normalized URL hash; lifecycle or site-control changes must increment that version. Public-request observability must be throttled and log hashes/reasons only—never site keys, raw URLs, or optimisation content.

## Follow bounded same-host sitemap redirects
Public and website-health sitemap availability checks use SitemapFetcher and evaluate the final HTTP response. Follow at most five HTTP(S) redirects on the original host/port; resolve relative Location values, stop loops, and reject credential-bearing or cross-host targets. Keep homepage/page redirect reporting unchanged; do not globally enable redirects on audit requests.
