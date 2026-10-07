---
paths:
  - app/Services/WebsiteCrawler.php
  - 'app/Services/Pixel*.php'
  - 'app/Services/{SitemapFetcher,ProspectWebsiteAnalyzer,WebsiteHealthAuditor}.php'
  - 'app/Services/WeeklyReport*.php'
  - app/Services/SearchConsoleClient.php
  - 'app/Services/GoogleAds*'
  - app/Services/GoogleAdsClient.php
  - 'app/Services/{WebsiteHealthAuditor,WebsiteCrawler,ProspectWebsiteAnalyzer}.php'
  - 'app/Services/{SeoImpactTracker,ContentWorkSelector,ContentGenerationPromptGenerator}.php'
  - app/Services/ContentGenerationPromptGenerator.php
  - app/Services/ProspectWebsiteAnalyzer.php
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

## Recheck selected Ads customer before campaign changes
Campaigns are created paused. Before enabling, pausing, or removing, fetch the current campaign from the website's selected Ads customer. Enabling requires an explicit tracking, ad, and budget check; removal requires its exact current name. Do not auto-retry uncertain Ads mutations.

## Edit live Ads from fresh account data
Read live campaign, ad, and budget values from the website's selected Ads customer before editing. Only change an unshared DAILY campaign budget; shared budgets can affect other campaigns. Keep ad text edits scoped to the current responsive search ad, preserving pinned assets and rejecting stale forms.

## Treat Search Console positions as sampled observations
Dashboard query summaries must show impressions alongside clicks and average position. Search Console returns rows by clicks with arbitrary zero-click ties, so fetch a bounded wider sample and order ties by impressions before showing ten. Label it a sample; never present a low-impression average position as a live or guaranteed rank.

## Score verifiable audit findings, not fixed content lengths
Do not mark a page unhealthy solely because its title or meta description exceeds a fixed character count or its body has fewer than a set number of words. Keep lengths as observations if useful; assess clarity and usefulness with context. A working skip link must target the page's main content and is an accessibility finding, not a GEO ranking claim. Never score optional llms.txt as a Google AI requirement.

## Content changes use a fourteen-day page cooldown, not measurement locks
Product decision 5 October 2026 supersedes measurement-period protection: changed pages/files require a full 14-day gap from their latest merge/direct change; SEO measurement status, query groups and unchanged control pages add no locks. Other open PRs retain overlap protection; exclude the current task's own PR. Deliver eligible independent parts of queued requests and defer protected supporting edits, preserving required sitemap/listing/inbound-link integration.

## Bound hosted Copilot prompts by UTF-8 bytes
Use a conservative 28,000-byte UTF-8 budget for hosted content prompts, leaving headroom below the 30,000-character boundary. Str::limit measures display width, so it cannot enforce a byte/character payload ceiling for arbitrary Unicode. Keep fixed safety and request-handling requirements before variable request text; truncate with mb_strcut to preserve UTF-8.

## Follow public audit redirects with destination validation
Prospect/public audit fetching follows at most five HTTP(S) redirects, including public cross-domain destinations, validating all A/AAAA answers at every hop and pinning the connection to a checked address. Reject credentials, private/reserved destinations, unsupported ports and loops. This does not change WebsiteHealthAuditor redirect reporting or SitemapFetcher’s same-host policy. Use final_url for page checks and public audit research; retain the submitted website_url.
