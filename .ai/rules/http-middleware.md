---
paths:
  - 'app/Http/Controllers/Admin/DashboardController.php,resources/views/admin/overview.blade.php,app/Http/Middleware/ResolveCurrentWebsite.php'
---

# Http Middleware

## Use the admin overview as the portfolio work hub
Admin overview defaults to shared, cross-website priorities and uses server-selected hub tabs for approvals, SEO results, automation and websites. site_id filters the portfolio without changing current_website_id. Admin queue actions returning to overview must also preserve that selection; normal website navigation remains authoritative. Reuse WebsiteActionCenter scoring/grouping and batch source reads, retain pagination/filter context, and never call external services or start work on GET.
