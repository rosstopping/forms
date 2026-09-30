---
paths:
  - 'resources/views/marketing/website-audit.blade.php,app/{Jobs/GenerateWebsiteAudit.php,Services/MarketingAudit*.php,Models/WebsiteAudit.php}'
---

# Marketing Jobs

## Enrich public audits with bounded measured data and conditional projections
Public audits now show technical health as passed checks / total checks, sitemap-listed page count with partial count marked +, flagged fixes, and DataForSEO UK Google estimates for ranking terms, top-10 terms, monthly visits and a 20-keyword sample with positions and monthly search volume. The two paid DataForSEO Labs requests are bounded and reused from a seven-day per-domain/market cache; skip the keyword request when the domain has no rankings. Store the snapshot on WebsiteAudit and never call paid APIs while rendering. If credentials/provider/sitemap data are unavailable, show unavailable rather than fabricated zero. Display a six-month organic-visit scenario only when sampled positions 11–30 have enough search volume, explain its assumptions, and never call it a forecast or guarantee. This supersedes the earlier count-only report rule; keep the concise managed SEO plan and Book a call with Ross CTA.

## Correction: include backlink summary as third paid request
The public audit enrichment uses up to three DataForSEO requests: domain overview, a 20-term ranked keyword sample when rankings exist, and backlink summary. Cache the combined paid data for seven days. This corrects the earlier two-request count in this file; the report-specific rule has the proper path scope.
