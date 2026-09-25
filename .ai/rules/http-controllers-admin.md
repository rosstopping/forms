---
paths:
  - app/Http/Controllers/Admin/ProspectController.php
  - app/Http/Controllers/Admin/OnboardingCallController.php
---

# Http Controllers Admin

## Keep sorted prospect list rows small
The prospect index must select only fields needed for list rendering before sorting and paginating. Do not SELECT * in that query: research JSON and email bodies can exhaust MySQL filesort memory. Preserve temperature priority and use created_at plus id for stable pagination.

## Keep emailed onboarding booking links usable
The onboarding call route must redirect authenticated users to marketing.booking_url even after their trial expires, after a completed call, or outside onboarding. Restrict booking-started tracking to active, unfinished trials and preserve the first timestamp; do not gate the public calendar redirect with a 404.
