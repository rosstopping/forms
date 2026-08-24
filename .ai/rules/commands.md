---
paths:
  - 'app/{Jobs,Services,Models,Mail,Console/Commands}/**/*ProspectOutreach*.php,routes/console.php,config/outreach.php'
---

# Commands

## Drive outreach from one idempotent due-action sequence
The scheduler may only dispatch states with next_action_at due; ProspectOutreachSequence owns all sales branching. Automatic messages must reserve a unique delivery idempotency key, snapshot subject/body, re-check central eligibility immediately before sending, and stop after the configured finite attempts. Meaningful engagement and protected/manual states always pause or stop cold advancement.
