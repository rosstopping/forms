---
paths:
  - 'app/{Jobs/StartContentGeneration.php,Services/ContentGenerationPromptGenerator.php,Services/SeoTargetKeywordSelector.php,Models/ContentGeneration.php}'
---

# App Jobs

## Snapshot and rotate strategic search targets
Content generations snapshot active target terms. Queued manual or competitor requests remain primary; otherwise select one target deterministically by priority, unranked status, weak position, and least recent selection. Update last_selected_at only after Copilot accepts the task, and keep fixed safety/action requirements inside the prompt budget.
