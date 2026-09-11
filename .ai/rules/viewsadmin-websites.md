---
paths:
  - 'app/{Console/Commands,Jobs,Mail,Models,Http/Controllers/Admin}/**/*Ranking*.php,resources/views/{admin/websites/**,emails/*ranking*},routes/web.php'
---

# Viewsadmin Websites

## Keep weekly ranking email delivery independent
Weekly ranking emails use websites.weekly_ranking_reports_enabled, independently of health_reports_enabled and seo_weekly_snapshots_enabled. Email rendering must only read stored ranking evidence and never trigger paid checks. Monthly ranking delivery retains its existing health-report subscription and viewer exclusion.
