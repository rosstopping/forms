---
paths:
  - '{app/Ai/Agents/WebsiteGrowthPlanner.php,app/Jobs/GenerateWebsiteGrowthPlan.php,app/{Models,Services,Http/Controllers/Admin,Http/Requests,Mail}/*WebsiteGrowthPlan*.php,app/Http/Controllers/WebsiteGrowthPlanReportController.php,resources/views/{admin/websites/growth-plan,website-growth-plans/*,emails/website-growth-plan}.blade.php}'
---

# Growth Planwebsite Growth Plans

## Generate ready client plans in one click
The user replaced the manual brief/editor/publish flow: staff click Generate plan; a deduplicated queued AI job uses bounded saved site context and automatically produces a ready version at an expiring signed report route. No report form, required manual context, or publish step. GET never generates or buys data. Keep measured baseline values outside AI output, validate keyword IDs and own-page destinations, distinguish suggested keywords and Search Console averages, and omit unsupported numerical six-month targets. Email remains an explicit separate action. This supersedes the manual review/publish requirements above.
