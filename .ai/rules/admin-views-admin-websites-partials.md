---
paths:
  - 'app/Http/Controllers/Admin/CopilotSdkRunController.php,app/Http/Controllers/Admin/WebsiteController.php,resources/views/admin/websites/partials/content.blade.php,config/copilot_sdk.php'
---

# Admin Views Admin Websites Partials

## Pause the temporary SDK test workspace
The Content workspace's title-only GitHub/Copilot SDK test is paused. Keep it hidden by default with copilot_sdk.workspace_enabled=false, and make its web start/status/resume endpoints return 404 while paused. Preserve normal Content queue and repository connections; the underlying pilot code can be explicitly re-enabled later.
