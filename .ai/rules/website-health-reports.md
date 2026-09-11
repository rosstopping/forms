---
paths:
  - 'app/Models/Website.php,resources/views/admin/websites/**,resources/views/admin/website-health-reports/**'
---

# Website Health Reports

## Gate delivery workspaces per website
Use websites.wordpress_enabled and websites.pixel_enabled as the source of truth for showing the WordPress and Pixel tabs. Remediation UI must describe only currently enabled and usable delivery paths; a paired WordPress connection labels the repository delivery path as WordPress, while GitHub remains available independently for non-WordPress sites.
