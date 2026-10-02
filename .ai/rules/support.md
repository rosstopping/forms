---
paths:
  - app/Support/WebsiteNavigation.php
---

# Support

## Keep Google Ads separate from Search performance
Google Ads is its own website navigation item. Resolve all admin.google-ads.* routes to a distinct google-ads section before query-tab handling; do not highlight Search performance or show a Search performance breadcrumb on Ads pages.
