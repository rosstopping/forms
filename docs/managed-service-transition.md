# Sitewell managed service transition

Agreed with Ross on 8 October 2026. This is the implementation plan and progress record.

## Product direction

Sitewell is an internal service management app. Ross and authorised staff operate it; customers receive weekly/monthly emails and have a small portal for progress, CRM leads and billing. Remove customer permission tiers and self-service SaaS onboarding.

## Agreed access model

| Account | Access |
| --- | --- |
| Ross | All websites, staff assignments, service packages, billing administration and global settings |
| Admin | One staff role, with all-site access or explicitly assigned sites |
| Customer | Assigned websites only; Overview, Leads and Billing |

Customers can manage lead status, tags and notes. They cannot operate integrations, campaigns, content queues, technical checks or website configuration. Manager and Viewer roles will be retired. Customers remain a separate account type, not administrators.

Website service packages, account permissions, billing records and email recipients are separate concerns. Ross controls each site's package and service state. Existing Stripe customers/subscriptions and website data must be preserved. Stripe webhooks must not overwrite a manually assigned website package.

## Findings from the existing system

- Much of `/admin` currently accepts any authenticated user. Individual controllers then rely on website membership and package checks.
- Admins bypass site permissions. Assigned-site restrictions must cover lists, reads, writes, exports, related records, integration callbacks and operational summaries.
- Packages currently belong to users. Website member changes can reassign the subscription sponsor (`websites.user_id`).
- Scheduled content, AI checks, competitor research and other tasks depend on the owner's membership. These checks need a website service equivalent before legacy removal.
- Report delivery includes every admin. Monthly reporting excludes viewers. Both need explicit site-scoped recipients.
- Email links lead into operational screens. Customer links must lead to the portal/report they received.
- Audit onboarding still contains account creation and a 14-day Growth trial.
- The local review found websites without subscription sponsors; production migration must account for these rather than guessing their package or activating paid work.

## Rollout and acceptance criteria

### 1. Website service foundation — complete

- Add website-level package, service status and optional end date.
- Backfill recognised packages from existing account settings without changing Stripe records, site assignments or automation flags.
- Preserve existing trial/complimentary end dates; keep ambiguous sites unassigned for review.
- Provide admin-only service settings, independently of billing and membership.
- Keep existing operational gates/schedulers until their replacements are tested. Adding the foundation alone must not start paid work or send emails.

### 2. Staff permissions and customer boundary — pending

- Add all-site/assigned-site staff access with Ross controlling staff assignments and global settings.
- Enforce operational routes as admin-only, with website-scoped authorisation throughout.
- Update site switching, portfolio summaries, CRM assignee lists, exports, downloads and integration callbacks.
- Preserve public audits, form ingestion, Pixel/WordPress machine endpoints and secured report links with their appropriate independent access checks.

### 3. Customer portal — pending

- Overview: results, completed work, next priorities and report history, reading stored reporting data without triggering paid research.
- Leads: website-scoped lists/details, status, tags and notes; keep lead filtering and basic CRM workflows.
- Billing: invoices, payment details and agreed service; remove self-service package selection.
- Separate customer navigation/layout and login destination. Existing emailed URLs need a safe replacement destination.
- Keep profile/password/logout and impersonation protections functional.

### 4. Website service delivery — pending

- Replace owner membership checks in schedulers/jobs with website service status, package and enabled features.
- Retain paid API opt-ins, cadence, budget limits, queue safety and delivery controls.
- Payment status remains visible to Ross; a webhook does not silently change the agreed package or remove invoice access.
- Add an admin billing workflow to set the customer's agreed recurring price separately from the website package. At this stage actual charges still come from the existing Stripe subscription; the website service form does not change its price. No admin price editor has been implemented yet.
- Stop exposing package upsells or locked operational previews to customers.

### 5. Reporting and recipients — pending

- Configure customer contacts and delivery preferences per website, independently of account access.
- Weekly/monthly customer emails use explicit recipients; admin copies respect site access.
- Remove customer operational/GitHub links and link to the matching portal report.
- Reuse saved weekly snapshots and persist monthly snapshots where necessary for consistent email/portal history.

### 6. Managed onboarding — pending

- Preserve public audit/email capture and sales follow-up.
- Replace automatic trials, countdowns and customer integration checklists with an admin conversion/setup workflow.
- Ross assigns the website, package, contacts and portal invitation when appropriate.
- Rework or suppress obsolete trial lifecycle emails without deleting engagement/history records.

### 7. Legacy cleanup — pending

- Remove Manager/Viewer controls, membership middleware, user-level package overrides and redundant trial flows after their replacements pass.
- Update existing tests and repository rules; retain historical websites, leads, reports and billing identifiers.
- Remove old columns only in a later migration after backfill/rollback requirements are verified.

## Verification

Test all-site admins, assigned-site admins, own-site customers, other-site customers and unauthenticated visitors across reads, writes, exports, nested records and callbacks. Test recipient isolation, scheduled service eligibility, preserved billing IDs, migration/backfill behaviour and report links. Use staged additive migrations so old and new behaviour can be verified before destructive cleanup.

## Progress

- Review and target model approved.
- Phase 1 implemented: `service_package`, `service_status` (active/paused/ended) and optional `service_ends_at` on websites, with admin-only controls in the Settings section.
- `hasActiveService()` and `hasServiceFeature()` provide the website-level eligibility API for the later scheduler transition. Existing consumers deliberately retain their current membership checks until that phase.
- Additive schema and separate idempotent data backfill migrations preserve account billing, memberships, schedules and paid-work flags. Existing explicit service settings are not overwritten.
- Both migrations applied locally: 13 sites received recognised active Complete packages; 10 ownerless sites remain unassigned for Ross to review. These counts describe the local database, not production.
- Verification: 46 affected feature tests passed (229 assertions), Pint completed, frontend build passed and diff whitespace checks passed. Tests cover customer rejection, admin configuration, invalid inputs, service expiry, paused/inactive services, safe backfill and Stripe independence.
- Next: staff all-site/assigned-site permissions and the customer route boundary, followed by the minimal portal. Viewer/Manager roles, customer operational routes and legacy automation gates have not yet been removed.
- Website user section simplified: admin-only name/email list, add-by-email and remove controls; role selectors, package grants and subscription sponsorship controls removed from that section. New attachments use the existing read-only customer role internally during the portal transition. Re-adding existing users preserves their historical permissions and billing settings.
- Member management is now admin-only and independent of package entitlement. The old role/package update endpoint rejects obsolete inputs and returns Gone for otherwise empty requests. Existing subscription sponsorship and the last-manager removal safeguard remain internally until access and billing are fully decoupled.
- User-section regression verification: 77 affected feature tests passed (392 assertions), including invitations, account setup, isolation, old package/role rejection, service settings and preserved billing. Pint and the frontend build passed. The full customer access-policy migration remains pending; this UI cleanup does not claim to have removed every legacy permission check.
- No production deployment or destructive schema cleanup has been performed.
