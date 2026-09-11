---
paths:
  - 'app/{Actions/ActivateWebsiteAuditTrial.php,Enums/OnboardingLifecycleStep.php,Models/{User.php,OnboardingLifecycleMessage.php},Services/OnboardingLifecycleManager.php,Notifications/OnboardingLifecycleNotification.php,Listeners/MarkOnboardingLifecycleMessageSent.php,Console/Commands/DispatchDueOnboardingLifecycleMessages.php,Http/Controllers/OnboardingLifecycleClickController.php},database/migrations/**/*onboarding_lifecycle*.php,resources/views/admin/onboarding-leads/**,routes/{web,console}.php,tests/Feature/{OnboardingLifecycleTest.php,OnboardingLeadTest.php}'
---

# Views Admin Onboarding Leads Feature

## Persist and suppress onboarding lifecycle messages
Create one unique ledger row per onboarding user and lifecycle step. Dispatch the day 0/2/4/7/10/13/14 customer sequence and day 17 admin follow-up idempotently; suppress stale rollout steps, Search Console/call reminders after completion, and all pending messages after conversion. Track queueing, mail-channel delivery, and signed CTA clicks without storing request metadata.
