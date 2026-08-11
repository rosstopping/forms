---
paths:
  - 'app/{Services/Github*,Http/Controllers/**/*Github*,Models/{GithubInstallation,WebsiteRepository,RemediationRun}.php}'
---

# Services Controllers

## Use short-lived GitHub App installation tokens
Persist GitHub installation IDs and repository metadata only. Generate installation access tokens just in time and never store them. Verify repository selections against the installation API and validate every webhook with X-Hub-Signature-256 before processing.
