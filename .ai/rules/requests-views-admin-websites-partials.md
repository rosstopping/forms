---
paths:
  - 'app/{Jobs/RunCopilotSdkTitle.php,Http/Controllers/Admin/CopilotSdkRunController.php,Http/Requests/StoreCopilotSdkRunRequest.php},resources/views/admin/websites/partials/content-sdk*.blade.php'
---

# Requests Views Admin Websites Partials

## Keep SDK workspace execution explicit and admin-only
Content > SDK test queues only the constrained title pilot for current admins. Recheck live repository authorization in the worker and publisher; retain a shared repository lock with the CLI, single paid attempt, persisted progress/usage, and publishing-only retries from validated output. Require a durable database/Redis queue whose retry_after exceeds the 240-second job timeout. The ordinary content pipeline does not switch engines.
