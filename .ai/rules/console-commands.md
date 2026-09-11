---
paths:
  - 'app/{Models,Services,Jobs,Http/Controllers}/**/*SeoTargetKeyword*.php,app/Console/Commands/DispatchWeeklySeoSnapshots.php'
---

# Console Commands

## Keep target rank checks explicit and opt-in
Saving a target keyword never calls DataForSEO. Manual checks are explicit; automatic top-100 desktop checks run only for active targets when the website's existing seo_weekly_snapshots_enabled setting is enabled. Preserve market dimensions and source labels on every observation.
