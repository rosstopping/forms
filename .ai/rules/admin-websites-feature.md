---
paths:
  - '{app/{Models/WebsiteSetup.php,Services/WebsiteSetupService.php,Http/Controllers/Admin/WebsiteSetupController.php,Http/Requests/*WebsiteSetupRequest.php},resources/views/admin/websites/{setup.blade.php,partials/setup-*.blade.php},tests/Feature/WebsiteSetupTest.php}'
---

# Admin Websites Feature

## Admin client setup saves drafts and applies settings explicitly
The admin-only wizard saves per-website steps in WebsiteSetup. Save/continue must not enable schedules, paid research or send customer messages; finish applies existing settings transactionally and idempotently, using owner membership and ContentSchedule readiness. Keep keywords additive with the 20-active-term limit; preserve archived terms, priorities and existing competitors. Do not silently enable paid SEO snapshots. Connection status comes from saved integration records; WordPress needs a repository. Bound the combined content guidance to the existing generator's 4,500-character budget so editorial instructions are not silently truncated.
