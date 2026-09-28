---
paths:
  - 'app/{Services/{CopilotSdkTitleRunner,GithubSdkPublisher},Console/Commands/TestCopilotSdkRepository,Models/CopilotSdkTestRun}.php,resources/copilot-worker/**'
---

# Copilot Worker

## Keep the first SDK repository test constrained to an in-memory title change
The admin-only copilot-sdk:test-repository command is an explicit title-only pilot, independent of hosted content/remediation actions. Read a verified regular HTML blob at a fixed commit, expose only in-memory tools, validate every byte outside the title, and publish only a draft PR through repository-scoped App credentials. Recheck user access before publishing and resume using the persisted run/commit/branch instead of another paid SDK call. This is not an OS sandbox: arbitrary file tools, shell commands, builds and repository instructions require a separately provisioned execution boundary.

## Count OpenAI cached input once in SDK budgets
The pinned Copilot runtime reports OpenAI inputTokens inclusive of cacheReadTokens (verified against the real runtime with a local completions stub). Enforce the OpenAI budget using inputTokens + outputTokens; retain cache counters as diagnostics, not additional tokens. Keep a nonzero-cache runtime regression when upgrading the SDK; other providers may report separate cache input.

## Use a compact title argument for the SDK smoke test
The title-only worker write tool takes the approved plain-text title, not a regenerated HTML document. Trusted code escapes and replaces the title while PHP independently validates the full result before publishing. This tests SDK tool dispatch and the draft PR pipeline, not general-purpose model-authored code editing; do not expand its scope implicitly.

## Run general SDK repository work on a separate worker
Ross chose a separate worker server for general SDK content/build execution, away from the production Sitewell Forge app. Digizu (rosstopping/digizu) is the first intended content-queue pilot; the title-only UI does not enable it. Provision and verify isolation before switching that website’s engine. Resume context and rollout steps are in docs/copilot-sdk-repository-plan.md.
