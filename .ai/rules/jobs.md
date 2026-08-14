---
paths:
  - 'app/Jobs/Sync*Copilot*.php,app/Jobs/SyncContentGeneration.php'
---

# Jobs

## Resolve Copilot pull requests by branch
Copilot artifact data.id is an artifact/database identifier, not a repository pull-request number. Use data.number only when supplied; otherwise resolve sessions.0.head_ref through GithubAppClient::pullRequestForHead before persisting a PR number or URL.
