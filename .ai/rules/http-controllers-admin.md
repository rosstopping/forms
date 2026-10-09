---
paths:
  - app/Http/Controllers/Admin/ProspectController.php
  - app/Http/Controllers/Admin/OnboardingCallController.php
  - app/Http/Controllers/Admin/ContentPlanController.php
  - app/Http/Controllers/Admin/WebsiteController.php
  - app/Http/Controllers/Admin/WebsiteMemberController.php
---

# Http Controllers Admin

## Keep sorted prospect list rows small
The prospect index must select only fields needed for list rendering before sorting and paginating. Do not SELECT * in that query: research JSON and email bodies can exhaust MySQL filesort memory. Preserve temperature priority and use created_at plus id for stable pagination.

## Keep emailed onboarding booking links usable
The onboarding call route must redirect authenticated users to marketing.booking_url even after their trial expires, after a completed call, or outside onboarding. Restrict booking-started tracking to active, unfinished trials and preserve the first timestamp; do not gate the public calendar redirect with a 404.

## Retry content without duplicating GitHub work
Admin retries retain the original generation, requester and daily reservation under the content plan lock. Existing task IDs only resume synchronization. Restart pre-task failures only in the original local-date slot after a definite rejection or before submission; ambiguous submission failures require reconciliation with GitHub. Keep scheduling, work cooldown and review safeguards intact.

## Load recent content runs with a narrow single-plan query
The website workspace has one content plan. Do not eager load contentPlan.generations with limit(8): Laravel builds a ROW_NUMBER window query that sorts full content_generations rows, including long prompts, and can exhaust MySQL sort memory. After loading the plan, query its eight newest generations directly with only the columns used by the activity view and set the relation.

## Website access invitations do not grant memberships
Website users is admin-managed add/remove access by email, without user role/package/sponsorship controls. New attachments use the safe read-only customer pivot during portal migration; re-adding existing users must preserve access and billing. Reject old role/complimentary membership fields and retire member update mutations. Legacy billing sponsorship and last-manager removal safeguards persist until the portal/access migration decouples them.
