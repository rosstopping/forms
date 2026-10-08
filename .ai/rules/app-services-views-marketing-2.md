---
paths:
  - '{app/Services/MarketingAuditResearch.php,resources/views/marketing/audit-email-gate.blade.php}'
---

# App Services Views Marketing 2

## Show homepage category grades and initial sitemap counts
User now wants ~ prefixed Google ranking estimates with no Estimated caption, Pages found, and separate score rings. Group saved checks into On-page SEO (Search essentials + Structured data), Crawl access (Discoverability), Usability (Accessibility), Security, and Response time (only response_time key). Scores are passed/total valid checks, grades use A+95/A85/B70/C50/D25/F thresholds; untested is an em dash, never a fabricated grade or AI/link score. Label grades as limited homepage checks. Initial queued research now counts sitemap pages with the existing bounded guarded counter and seven-day URL cache, stores partial/host mismatch metadata, and full research reuses it; this supersedes deferred sitemap counting. Display matching-domain sitemap pages, + for partial counts, dash for unavailable; reads never fetch and competitor/AI checks remain after email capture.
