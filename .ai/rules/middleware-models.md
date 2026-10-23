---
paths:
  - '{app/Services/{ManualContentSchedule,AgentWorkOverview}.php,app/Http/Middleware/AuthenticateSitewellAgent.php,app/Models/ContentPlan.php}'
---

# Middleware Models

## Manual content cadence prompts staff without starting work
Manual uses the saved local weekday/hour for due-today attention independently of enabled (automatic execution stays off). Reminders default on, support skipping the local date, and are handled by active manual work or completion that local day. Empty queues must remain visible; suggestions/planned work still need explicit queue approval. The read-only agent API uses a revocable SHA-256 bearer-token hash tied to a current admin and Website::accessibleTo(), never triggers research/generation, and must not expose stored prompts or integration credentials.
