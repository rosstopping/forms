---
paths:
  - '{app/Jobs/{GenerateWebsiteAudit,GenerateWebsiteAuditFullReport,CheckMarketingAuditAiVisibility}.php,app/Services/MarketingAuditResearch.php,app/Http/Controllers/FreeSiteAuditController.php,resources/views/{marketing/website-audit,admin/onboarding-leads/index}.blade.php}'
---

# Website Auditadmin Onboarding Leads

## Run private audit research only when Ross requests the full report
The October 2026 on-demand full report supersedes automatic competitor/backlink/sitemap-count/AI enrichment. Initial anonymous audits retain health findings, screenshot, contact discovery and the SEO overview plus up to ten keywords in positions 11–30 needed for the public six-month scenario. Defer page-one and fallback rankings, backlink summary, sitemap counting, competitor comparisons and sampled AI checks. Admin Onboarding's View full report is a CSRF-protected POST that queues unique research once and redirects to the full view; GET/refresh/public/email/share access never starts paid work. Persist queued/running/completed/failed full_report status in insights, preserve public audit readiness and support explicit failure retries. AI jobs require requested full research and pending AI status, including old queued jobs. Reuse existing legacy full results and seven-day matching SEO/competitor/AI caches; complete missing initial SEO only on explicit full requests. Admin progress polling refreshes saved results. Signed share links stay read-only.
