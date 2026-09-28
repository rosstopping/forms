---
paths:
  - 'app/{Services/{CopilotSdkTitleRunner,GithubSdkPublisher},Console/Commands/TestCopilotSdkRepository,Models/CopilotSdkTestRun}.php,resources/copilot-worker/**'
---

# Copilot Worker

## Keep the first SDK repository test constrained to an in-memory title change
The admin-only copilot-sdk:test-repository command is an explicit title-only pilot, independent of hosted content/remediation actions. Read a verified regular HTML blob at a fixed commit, expose only in-memory tools, validate every byte outside the title, and publish only a draft PR through repository-scoped App credentials. Recheck user access before publishing and resume using the persisted run/commit/branch instead of another paid SDK call. This is not an OS sandbox: arbitrary file tools, shell commands, builds and repository instructions require a separately provisioned execution boundary.

## Count OpenAI cached input once in SDK budgets
The pinned Copilot runtime reports OpenAI inputTokens inclusive of cacheReadTokens (verified against the real runtime with a local completions stub). Enforce the OpenAI budget using inputTokens + outputTokens; retain cache counters as diagnostics, not additional tokens. Keep a nonzero-cache runtime regression when upgrading the SDK; other providers may report separate cache input.
