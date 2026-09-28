---
paths:
  - '{app/Services/GithubRepositoryPilot.php,app/Http/Controllers/Admin/{GithubConnectionController,WebsiteRepositoryController}.php,config/copilot_sdk.php}'
---

# Services Http Controllers Admin

## Keep customer GitHub connection pilot separate from SDK execution
The new connection flow is a default-off admin pilot requiring explicitly allowlisted Sitewell user IDs; apply the same eligibility check at connect, callback, listing and saving. Ordinary customers and unlisted admins retain the legacy flow. Connection success does not enable SDK remediation: existing actions still use hosted Copilot until isolated execution and publishing are implemented. Never widen rollout or fall back between execution engines implicitly.

## All admins automatically use the repository pilot
Supersedes the earlier default-off flag and user-ID allowlist: at the user's request every Sitewell admin automatically uses the new repository connection flow, with no connection environment settings. Non-admins retain legacy connections. Check the current admin role at connect, callback, listing and saving; SDK execution remains independently disabled by default and is not enabled by a connection.
