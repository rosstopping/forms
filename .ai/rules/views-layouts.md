---
paths:
  - 'app/Http/Controllers/Admin/DashboardController.php,resources/views/admin/{dashboard,overview}.blade.php,resources/views/layouts/app.blade.php'
---

# Views Layouts

## Keep the admin operations overview separate from website Overview
The admin.overview route is global-admin-only and aggregates upcoming schedules and pending reviews across websites, independent of current_website_id. Link it as Admin overview in desktop and mobile navigation. Keep admin.dashboard scoped to the selected website. Render from stored data without external API calls; opening the admin overview must not change an existing valid site selection.
