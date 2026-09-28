---
paths:
  - 'app/{Services/{CopilotSdkTitleRunner,GithubSdkPublisher},Console/Commands/TestCopilotSdkRepository,Models/CopilotSdkTestRun}.php,resources/copilot-worker/**'
---

# Copilot Worker

## Keep the first SDK repository test constrained to an in-memory title change
The admin-only copilot-sdk:test-repository command is an explicit title-only pilot, independent of hosted content/remediation actions. Read a verified regular HTML blob at a fixed commit, expose only in-memory tools, validate every byte outside the title, and publish only a draft PR through repository-scoped App credentials. Recheck user access before publishing and resume using the persisted run/commit/branch instead of another paid SDK call. This is not an OS sandbox: arbitrary file tools, shell commands, builds and repository instructions require a separately provisioned execution boundary.
