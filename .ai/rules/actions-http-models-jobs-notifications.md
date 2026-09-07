---
paths:
  - 'app/{Actions,Http,Models,Jobs,Notifications}/**/*.php'
---

# Actions Http Models Jobs Notifications

## Onboarding trial lifecycle
Website review onboarding remains anonymous until the visitor confirms their email. Confirmation creates or attaches the website and starts a no-card 14-day Essential trial marked with onboarding_status=trial_active. Trial access and scheduled weekly health reports must stop once onboarding_trial_ends_at / membership_current_period_end has passed.

## Growth onboarding trial lifecycle supersedes Essential
The onboarding trial is now Growth, not Essential. Email confirmation starts a no-card 14-day Growth trial marked onboarding_status=trial_active; access and weekly health reports stop at the trial end. Do not automatically enable paid DataForSEO snapshots.
