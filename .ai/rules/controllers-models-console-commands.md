---
paths:
  - '{app/Services/Agent*.php,app/Http/{Controllers/AgentContentTaskController.php,Requests/*AgentContentTaskRequest.php},app/Models/AgentContentTask.php,app/Console/Commands/ManageAgentTask.php}'
---

# Controllers Models Console Commands

## Delegate due empty-queue selection to the agent but deliver draft PRs only
Ross authorised evidence-based task selection and local implementation for due manual websites with empty approved queues on 23 October 2026. The agent may perform business/coverage/repository/open-PR review and reserve one persisted task per website/local date; reuse ManualContentRequest and its strategy, immutable budget, cooldown and overlap checks. Require a recorded host/project and scoped recent saved evidence; retries resume the same task, never generate filler or launch paid/hosted execution. The CLI verifies origin, branch, commit and an open changed GitHub draft before reporting; API records that as agent-reported evidence, not independent GitHub verification. Keep reservations for human review; no automatic complete, merge, publication or SEO-live measurement. Blocked work stays visible on hold.
