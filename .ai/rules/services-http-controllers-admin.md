---
paths:
  - '{app/Services/GithubRepositoryPilot.php,app/Http/Controllers/Admin/{GithubConnectionController,WebsiteRepositoryController}.php,config/copilot_sdk.php}'
---

# Services Http Controllers Admin

## Keep customer GitHub connection pilot separate from SDK execution
The new connection flow is a default-off admin pilot requiring explicitly allowlisted Sitewell user IDs; apply the same eligibility check at connect, callback, listing and saving. Ordinary customers and unlisted admins retain the legacy flow. Connection success does not enable SDK remediation: existing actions still use hosted Copilot until isolated execution and publishing are implemented. Never widen rollout or fall back between execution engines implicitly.
