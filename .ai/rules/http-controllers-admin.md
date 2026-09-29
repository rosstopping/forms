---
paths:
  - app/Http/Controllers/Admin/ProspectController.php
  - app/Http/Controllers/Admin/OnboardingCallController.php
  - app/Http/Controllers/Admin/ContentPlanController.php
---

# Http Controllers Admin

## Keep sorted prospect list rows small
The prospect index must select only fields needed for list rendering before sorting and paginating. Do not SELECT * in that query: research JSON and email bodies can exhaust MySQL filesort memory. Preserve temperature priority and use created_at plus id for stable pagination.

## Keep emailed onboarding booking links usable
The onboarding call route must redirect authenticated users to marketing.booking_url even after their trial expires, after a completed call, or outside onboarding. Restrict booking-started tracking to active, unfinished trials and preserve the first timestamp; do not gate the public calendar redirect with a 404.

## Retry content without duplicating GitHub work
Admin retries retain the original generation, requester and daily reservation under the content plan lock. Existing task IDs only resume synchronization. Restart pre-task failures only in the original local-date slot after a definite rejection or before submission; ambiguous submission failures require reconciliation with GitHub. Keep scheduling, work cooldown and review safeguards intact.
