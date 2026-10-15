---
paths:
  - '{app/{Models,Services,Http/Controllers/Admin,Http/Requests,Mail}/*WebsiteGrowthPlan*.php,app/Http/Controllers/WebsiteGrowthPlanReportController.php,resources/views/{admin/websites/growth-plan,website-growth-plans/report,emails/website-growth-plan}.blade.php}'
---

# Reportemails

## Keep client growth plans reviewed and versioned
Growth plans are staff-prepared reports for websites with attached signed-up customers, not prospect proposals. Drafts use stored evidence only; publishing freezes a version and emailing is a separate explicit action to an attached customer (including read-only customers receiving this report). Keep intended pages separate from observed ranking URLs. Six-month keyword ranges are optional explained planning targets, never automatic predictions; public access requires an expiring signed link to a published version. Creating plans must not enable automation, change tracked keywords, or send email.

## Allow active managed clients to prepare plans without customer accounts
Staff may open the plan editor for any accessible website. Generating and publishing require either an active managed service or an attached customer account; an active client need not have a portal user yet. Show setup guidance instead of a 422 on GET. Emailing still requires an attached non-staff customer recipient. This supersedes the earlier attached-customer prerequisite for preparing growth plans.
