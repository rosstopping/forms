# Outreach Automation Progress

Last updated: 24 August 2026

## Status

- [x] Phase 1 — Existing-system inspection and implementation plan
- [x] Phase 2 — Lifecycle and engagement-scoring infrastructure
- [x] Phase 3 — Connect opens, clicks, and report interactions
- [x] Phase 4 — Automated cold follow-up sequence
- [x] Phase 5 — Hot-lead and personalised-video workflow
- [x] Phase 6 — Post-video follow-up and manual recommendations
- [ ] Phase 7 — Outreach dashboard and activity timeline
- [ ] Phase 8 — Full lifecycle and duplicate-send test coverage

No application structure was changed in Phase 1. This document is the only Phase 1 codebase change.

## Phase 2 implementation

Completed on 24 August 2026.

- Added backed enums for lifecycle state, automation status, sequence step, engagement event type, and stop reason.
- Added one `prospect_outreach_states` row per prospect with lifecycle, score, automation controls, sequence progress, due-action timestamps, video/follow-up timestamps, and stop/manual-action context.
- Added immutable `prospect_engagement_events` with optional delivery/link attribution, event fingerprints, score deltas, source, metadata, and occurrence time.
- Backfilled all existing prospects using their current status, lead temperature, suppression, send, scheduling, and follow-up fields. The local database migration produced 38 state rows for 38 prospects.
- New prospects now receive an outreach state automatically. Existing analysis, approval, scheduling, sending, suppression, generic status editing, and inbound free-audit paths synchronise the new state.
- Added `config/outreach.php` for timing, thresholds, bounded event scoring, maximum attempts, automation enablement, ignored sources, and the first template defaults.
- Added `ProspectEngagementScorer` with per-prospect row locking, unique fingerprint idempotency, first/repeat award caps, zero-score scanner events, configurable temperature bands, manual score adjustments, and manual temperature overrides.
- Added `ProspectLifecycleManager` for protected automatic transitions, manual lifecycle changes, pause/resume/stop, future-opportunity dates, temperature overrides, and synchronisation with legacy prospect fields.
- Added `ProspectOutreachEligibility` as the shared stop-policy boundary. The existing sender now rejects paused, stopped, protected, suppressed, unapproved, not-due, or incomplete prospects through this service.
- Reply events now atomically mark the prospect replied, stop automation, clear pending scheduling/follow-up fields, and record timeline activity. Automatic email-provider reply ingestion remains a later-phase integration.
- Inbound free-audit prospects are explicitly paused so transactional audit delivery cannot place them into cold outreach automation.
- Recorded the lifecycle/score/timeline separation as a durable shared project rule.
- Verification: 63 focused prospect/free-audit tests pass with 334 assertions; Pint completed successfully.
- Full-suite verification after Phase 6: 472 of 474 tests pass. The two failures reproduce when `WebsiteAiChatTest` is run alone and concern its existing fake AI-provider exception/rate-limit handling; no outreach code is present in either failure path.

## Phase 3 implementation

Completed on 24 August 2026.

- Connected the existing signed open endpoint to `ProspectEngagementScorer`. Delivery counters and first-open activity remain intact while every request now receives an idempotent ledger fingerprint and bounded score decision.
- Connected tracked link kinds to distinct engagement events: website audit, personalised video, booking page, and future Sitewell/pricing link kinds.
- Replaced unconditional click-to-hot promotion with the configurable Phase 2 weights and score bands. Hot notifications still send once when a genuine scored click or audit revisit crosses the hot threshold.
- Added configurable meaningful-repeat windows. Immediate repeated opens/clicks remain queryable but receive zero additional points; a repeat is eligible only after the configured interval and only up to its award cap.
- Added transient scanner classification using conservative request headers and known scanner user-agent patterns. Scanner events are recorded with zero points, but IP addresses and user-agent values are never persisted.
- New tracked audit links include their link UUID inside the already signed report URL. This lets direct revisits to that signed URL be attributed to the correct prospect and link.
- The first audit click scores at the tracking redirect. The immediately following report load is recorded but receives no repeat award; a later meaningful revisit can receive the configured revisit score.
- Free-audit and other directly generated report URLs remain unattributed and do not create cold-outreach engagement events.
- Protected outcomes such as customer, replied, and not interested continue recording historical engagement without being reclassified or generating hot-lead notifications.
- Recorded the configurable intent-classification behavior as a durable shared project rule, superseding the earlier unconditional click-to-hot shortcut.
- Verification: 66 focused prospect/free-audit tests pass with 367 assertions.

## Phase 4 implementation

Completed on 24 August 2026.

- Added delivery message types for initial, cold follow-up, final follow-up, personalised video, and post-video follow-up messages.
- Every live delivery now snapshots its subject/body and records message type, status, failure details, and an optional globally unique idempotency key. Template edits therefore do not rewrite historical email content.
- Extended the existing sender instead of adding a second mail path. Initial and automatic messages share approval, suppression, pause/stop, recipient, tracking-link, and per-prospect send-lock safeguards.
- Failed logical deliveries remain queryable and are reused on retry; a successful replay cannot create or send a duplicate delivery for the same sequence action.
- Initial email delivery now schedules the first lifecycle evaluation using `cold_retry_days` rather than a fixed one-week reminder.
- Added `outreach:dispatch-due`, which queries only active states whose indexed `next_action_at` is due and dispatches one unique `EvaluateProspectOutreach` job per prospect.
- Added the scheduler entry as a one-server, non-overlapping minute trigger. The schedule contains no sales decisions; all branching lives in `ProspectOutreachSequence`.
- The sequence sends one cold follow-up, waits the configured final delay, sends one lightweight final follow-up, waits once more, then stops automation as Exhausted. It honours the configured maximum attempt count.
- The cold retry may reuse the approved initial draft; the final follow-up is rendered from the configurable template with contact/company placeholders.
- Meaningful engagement clears the cold action and records why automation paused. Paused, stopped, replied, customer, not-interested, suppressed, unapproved, disabled, and not-due prospects remain unsent.
- Applied the Phase 4 delivery migration to the local database.
- Verification: 69 focused outreach tests pass with 380 assertions; Pint and `git diff --check` pass.

## Phase 5 implementation

Completed on 24 August 2026.

- Crossing the configured hot threshold now transitions an eligible prospect to Needs Personalised Video, pauses cold automation, clears the due cold action, and records a queryable queue activity.
- Later engagement cannot accidentally move Needs Personalised Video or Video Sent prospects back to the generic Hot state.
- Added a prominent Needs Personalised Video queue to the outreach index. Cards show company/domain, engagement score, recent scored reasons and timing, initial-send timing, audit access where available, and an Add Personalised Video action.
- Added the prospect-level manual video form with video URL, editable subject/body, immediate send, and UK-time scheduling.
- Personalised video messages use the existing tracked mailable, sender, delivery ledger, cache lock, approval/stop safeguards, and signed links. The video link remains a distinct tracked link/event kind.
- Video URL and thumbnail are stored on the prospect without rewriting or re-approving the original cold-email draft. The actual video email subject/body are snapshotted on its delivery.
- Scheduled videos reserve the logical delivery before dispatch. Rescheduling updates that reservation, while timestamp checks make stale jobs no-ops.
- Only an explicit authenticated administrator action can create, schedule, or send a personalised video. The lifecycle evaluator has no branch that can generate or auto-send one.
- Successful delivery records Video Sent, its send timestamp, a timeline event, and the future post-video evaluation date needed by Phase 6.
- Replies, suppression, customers, other stopped states, duplicate video attempts, and stale scheduled jobs cannot send the video.
- Applied the Phase 5 scheduling migration to the local database.
- Verification: 75 focused outreach tests pass with 428 assertions; Pint and `git diff --check` pass.

## Phase 6 implementation

Completed on 24 August 2026.

- Extended the central sequence evaluator with the Video Sent → Post-video Follow-up branch. It uses the configured three-day delay and the existing unique due-action job; no additional cron logic was introduced.
- Added exactly one configurable conversational post-video message. It snapshots its subject/body and reserves the fixed `post_video_follow_up:1` idempotency key.
- Post-video messages deliberately contain only the conversational body and open tracking; they do not repeat the video, audit, or booking cards.
- The sender re-checks approval, suppression, reply/customer/terminal state, video-sent state, sequence step, automation status, and prior post-video delivery immediately before sending.
- Reply events and manual Replied status transitions continue to clear the due action and stop this branch before delivery.
- After the single follow-up, `next_action_at` is cleared. There is no lifecycle branch capable of sending another automatic post-video email.
- Added a video-send score baseline and an evidence builder that considers only positively scored audit, Sitewell, personalised-video, and booking interactions occurring after the video was sent. Opens and scanner/zero-score events are excluded.
- Strong engagement before the due follow-up is retained as evidence; the configured follow-up still sends once, then the prospect is surfaced for manual handling. Strong engagement arriving later surfaces them immediately.
- Manual recommendations persist exact reasons, counts, and last-occurrence timestamps in `manual_follow_up_reason`, transition to Highly Engaged, pause automation, clear future actions, and write one timeline activity. Later engagement refreshes the stored reasons without duplicating that activity.
- Added Manual Follow-up Recommended cards to the outreach index and a prominent explanation panel on the prospect page. Both explain why the lead was surfaced and how recent each signal was.
- Applied the Phase 6 state migration to the local database.
- Verification: 81 focused outreach tests pass with 472 assertions; Pint and `git diff --check` pass.

## Phase 1 findings

### Runtime and application shape

- Laravel 13.24, PHP 8.4, Pest 5, Horizon 5, and the database queue/cache infrastructure are installed.
- The application uses a custom Blade admin area rather than Nova, Filament, or Livewire.
- Prospect discovery, research, approval, sending, and engagement tracking already form a coherent feature area under `App\Models`, `App\Services`, `App\Jobs`, `App\Mail`, and `App\Http\Controllers\Admin`.
- Existing project rules require cold outreach to remain approval-first, keep lead temperature separate from lifecycle status, treat opens as weak intent, treat verified clicks as strong intent, and notify administrators only on the first transition to hot.

### Prospect creation and qualification

- `Prospect` is the central aggregate. It stores ownership, optional website association, business/contact details, research results, the approved email draft, video fields, lifecycle-like status timestamps, lead temperature, suppression, and follow-up dates.
- `Prospect::STATUSES` currently contains `new`, `researched`, `drafted`, `approved`, `contacted`, `replied`, `converted`, and `not_interested`.
- `lead_temperature` is already independent and accepts `cold`, `warm`, or `hot`.
- Prospects can be created manually, from an existing managed website, from map discovery, from SEO discovery, or from the inbound free-audit flow.
- Discovery import is deliberately manual and deduplicates suitable candidates before dispatching analysis. SEO imports preserve evidence in `ProspectActivity` metadata.
- `AnalyzeProspect` runs queued website analysis, discovers public contact details, writes the existing initial outreach draft, resets approval, and records activity. The current initial email wording matches the requested flow and should be retained.
- Free-site-audit prospects are consented inbound leads with their own automatic transactional result email. They must be excluded from cold-sequence automation.

### Approval, scheduling, and sending

- Draft approval is explicit through `ProspectApprovalController`; editing the subject, body, or video URL resets approval and cancels a pending scheduled send.
- `ProspectScheduleController` and the bulk action controller convert UK local time to UTC, store `scheduled_send_at`, record an activity, and dispatch a delayed `SendScheduledProspectOutreach` job after commit.
- The delayed job verifies that its stored timestamp still matches the prospect. Rescheduling therefore makes stale jobs no-ops.
- `ProspectOutreachSender` is the shared immediate/scheduled send boundary. It checks approval, follow-up eligibility, suppression, recipient address, and link availability.
- Sending is guarded by a per-prospect cache lock. The sender creates tracking data before mail delivery, deletes that delivery if mail throws, then marks the prospect `contacted`, records `sent_at`, clears `scheduled_send_at`, sets `next_follow_up_at` to one week, and writes a `sent` activity.
- Follow-up sending is currently manual. A previously contacted prospect becomes send-eligible only when `next_follow_up_at` is due. Sending reuses the same prospect draft and creates another delivery.
- There is no central outreach scheduler entry. Delayed jobs schedule initial messages individually; `next_follow_up_at` is currently only a reminder/eligibility field.
- The mailable sends synchronously inside the queued scheduling job or web request. It is not itself queued.

### Deliveries, links, and tracking

- `prospect_outreach_deliveries` records one row per live email, with a unique public UUID, recipient, sent time, first/last open and click times, and aggregate counters.
- `prospect_outreach_links` records the tracked links for each delivery, also using unique public UUIDs. The existing kinds are `website_audit`, `showcase_video`, and `book_call`.
- A database unique constraint permits only one link of each kind per delivery.
- `ProspectOutreachTracker::createDelivery()` creates the correct links for the content available on the prospect. Admin test emails pass no delivery and are intentionally untracked.
- Signed public routes drive the one-pixel open endpoint and tracked-link redirect endpoint. Tracking stores no IP address or user agent.
- Open and click counter changes use transactions and row locks. The first open writes one activity and promotes only cold to warm. A verified click writes one activity per link's first click, promotes any non-hot lead to hot, and triggers the queued `ProspectBecameHot` notification only on that transition.
- Repeat opens/clicks update counters and last timestamps but do not currently create repeat activities, score intent, distinguish scanner traffic, or trigger another lifecycle decision.
- The audit URL itself is a temporary signed report route. Visits reached through an email are counted by the tracked redirect before the report loads. Direct report revisits after the redirect are not independently attributable or recorded.
- Sitewell/pricing links are not currently present in the outreach template or tracking-link model.

### Personalised video

- `Prospect` already stores `showcase_video_url` and a fetched Loom thumbnail URL.
- The same generic outreach mailable conditionally adds a tracked video card when a video exists.
- Adding or changing a video currently changes the main prospect draft, resets approval, and uses the same send action and lifecycle fields as initial outreach. There is no distinct video message type, video-sent timestamp, video queue, or post-video sequence.
- The existing generic mailable should be split or made template/message-type aware without replacing the initial-email workflow.

### Replies and manual controls

- The database and status list have `replied_at`, `replied`, `not_interested`, `converted`, and `suppressed_at` concepts.
- The edit screen can manually change the generic status, set a next-follow-up date, and suppress all email. Suppression cancels `scheduled_send_at`.
- No inbound-email provider webhook, mailbox polling, message identifier, reply detector, or dedicated “mark replied” action exists.
- Changing a status to replied/not interested/converted does not itself clear pending scheduling or suppression fields. The current sender eligibility check also does not reject these terminal statuses, so future automation must centralise and strengthen stop-state rules.
- Pause/resume, future opportunity, customer, pilot, force temperature, score adjustment, and manual-follow-up-recommended actions do not yet exist.

### Activity and admin UI

- `prospect_activities` is already the queryable audit trail and supports an actor, type, description, metadata JSON, and timestamps. It is indexed by prospect and creation time and is suitable for retaining lifecycle history.
- Existing activity types cover creation/import, analysis, approval, draft updates, scheduling/cancellation, sends, first open, first click, test email, and free-audit events.
- The prospect detail page renders the activity feed, current stage and temperature, latest delivery totals, each tracked link's totals, draft controls, scheduling, sending, suppression, notes, and audit findings.
- The prospect index has status/temperature summaries, filters, hot-first ordering, and bulk approve/research/schedule/send/delete actions.
- The main dashboard is currently a website-health dashboard only. There are no attention queues or outreach priority cards.

### Existing safeguards to preserve

- Explicit approval is required and draft edits invalidate it.
- Suppressed prospects cannot be sent to.
- Test messages are untracked.
- Stale delayed jobs no-op after a reschedule or cancellation.
- A cache lock serialises sends per prospect.
- Tracking updates use database locks.
- Signed tracking/report routes prevent arbitrary event mutation and report access.
- Hot notifications are sent once per cold/warm-to-hot transition.
- Public tracking intentionally avoids IP addresses and user agents.

### Gaps and risks to address

- `status` mixes research, outreach, and sales outcomes; adding every requested concept to it would make transitions ambiguous. A dedicated outreach state is preferable while the current prospect status remains compatible during migration.
- `sent_at`, `scheduled_send_at`, `next_follow_up_at`, and the stored subject/body are prospect-wide, so they cannot describe individual sequence steps or message variants reliably.
- The cache lock prevents concurrent execution, but there is no durable idempotency key proving that a particular sequence step was sent exactly once. A retry after a process failure at the wrong point can still be ambiguous.
- Engagement counters are not an immutable event ledger and cannot support configurable scoring, deduplication/fingerprints, score corrections, or explanations.
- Opens and clicks can be inflated indefinitely. Repeat activity is not classified into bounded first/repeat/meaningful awards.
- Any verified click currently makes a lead hot, which conflicts with the proposed configurable scoring bands and differentiated link weights.
- Direct report revisits cannot currently be distinguished from the first tracked email click.
- There is no automatic reply ingestion. Manual reply handling is therefore required in the first safe release unless an email provider integration is selected.
- Terminal manual statuses are not yet enforced at the single send-policy boundary.
- The generic prospect video fields and generic mailable cannot reliably distinguish an initial email, cold retry, final follow-up, personalised video, and post-video follow-up.
- The current scheduled-send job catches only `LogicException`; infrastructure failures retry, but it has no explicit failure audit callback.

## Recommended target design

### Keep and extend

- Keep `Prospect` as the lead/business aggregate, the discovery and qualification flows, explicit approval, `ProspectActivity`, signed tracking routes, `ProspectOutreachDelivery`, `ProspectOutreachLink`, the report page, administrator notifications, and the current initial email draft.
- Keep lifecycle state and engagement temperature/score separate. Preserve `lead_temperature` temporarily as a denormalised compatibility field while queues and screens migrate.
- Reuse `ProspectActivity` as the human-readable lifecycle timeline. Add a separate immutable engagement-event ledger because scoring and event deduplication have different responsibilities.

### Add in Phase 2

- Add backed enums for lifecycle state, automation status, sequence step/message type, engagement event type, and stop reason. Names should reflect the existing vocabulary where practical.
- Add one `prospect_outreach_states` row per eligible prospect. Suggested core fields: lifecycle state, engagement score, automation status, sequence step, follow-up attempt count, next action time, last outreach/engagement times, initial/video/post-video timestamps, future-opportunity date, manual-follow-up time/reason metadata, stopped time, stop reason, and optimistic/processing metadata if needed.
- Add immutable `prospect_engagement_events` with prospect, optional delivery/link, event type, source, fingerprint/idempotency key, score delta, occurred time, and metadata. Enforce a unique fingerprint where an external or derived event must be awarded once.
- Extend deliveries with a message type/sequence step, template snapshot or rendered subject/body, scheduling/sent/cancelled/failed timestamps, and a durable idempotency key unique to the prospect/campaign/step/attempt.
- Introduce a single eligibility/stop policy shared by manual sends, scheduled sends, and automation. Replied, not interested, future opportunity, customer, pilot, closed, exhausted, suppressed, or paused prospects must not pass normal automated-send eligibility.
- Put defaults in one `config/outreach.php` structure: delays, thresholds, bounded event awards, maximum attempts, auto-follow-up switch, template keys/content, scanner/deduplication windows, and future campaign override placeholders.
- Add a transition/evaluation service that owns state changes and writes `ProspectActivity` in the same transaction. Manual terminal states must be protected from automated reclassification.

### Central due-action process

- Add one scheduled command, for example `prospects:evaluate-outreach`, running every few minutes with `onOneServer()` and `withoutOverlapping()`.
- The command should query indexed `next_action_at <= now()` rows in bounded chunks and dispatch one unique evaluation job per outreach-state row.
- `EvaluateProspectOutreach` should acquire a durable per-prospect lock, reload state, apply the stop policy, and delegate the decision to a lifecycle/sequence service.
- The lifecycle service—not the schedule definition—decides whether to wait, send a cold retry/final follow-up, exhaust/nurture, request a video, send the already-authorised post-video follow-up, or recommend manual follow-up.
- Actual sends should use an outbox/delivery row with a unique idempotency key. Reserve the delivery atomically before sending, and make retries inspect its status so the same logical step cannot create two messages.

### Engagement model

- Translate tracking input into bounded semantic events: first open, repeat open award (capped), first link click by kind, meaningful revisit by kind (capped and separated by a configurable time window), reply, and optional scanner-suspected events with zero score.
- Recalculate/apply score through one scoring service using configuration. Store each applied delta on the immutable event for explainability.
- Initial defaults should follow the requested weights and configurable cold/warm/hot bands. Opens remain weak; audit/site clicks, video clicks, and booking clicks carry progressively stronger weight.
- Derive temperature from score unless manually overridden. Never let automated temperature updates overwrite a protected lifecycle/terminal status.
- Fire a lifecycle evaluation after committed meaningful engagement so hot leads are surfaced promptly rather than waiting only for the periodic command.

### Message templates and sequence

- Retain the current initial draft and approval process.
- Represent cold retry, final follow-up, personalised video, and post-video follow-up as distinct message types with configurable templates and subject strategies.
- Snapshot the actual subject/body and destination links onto each delivery so historical emails remain auditable after a template or prospect draft changes.
- Automated cold and post-video messages may use approved/configured templates, but personalised videos are never auto-sent: the operator must provide the URL, review/edit the message, and choose send now or schedule.

### Reply strategy

- First implement a dedicated manual “mark replied” transition that atomically sets the state/timestamp, pauses automation, cancels pending deliveries/actions, and records activity.
- Add provider-driven automatic reply detection only after confirming the production mail provider and its signed inbound-event/webhook capabilities. Persist provider message IDs on outbound deliveries to correlate replies safely.

### Dashboard and timeline

- Add an admin outreach dashboard or outreach section driven by indexed state/event queries: needs video, manual follow-up recommended, warm leads, recent replies, booking intent, automatic follow-ups sent today, and moved-to-nurture today.
- Queue cards should eager-load only the latest relevant activities/events and explain why each lead is present using stored event facts, not reconstructed application logs.
- Continue rendering `ProspectActivity` as the human timeline, with score-change metadata and links to the relevant delivery/event where useful.

## Phase-by-phase implementation plan

### Phase 2 — State and scoring foundation

1. Add enums, configuration, migrations, models, relationships, factories, and state backfill rules.
2. Add the immutable engagement ledger and bounded scoring service.
3. Add lifecycle transition and central send-eligibility services.
4. Add manual pause/resume/stop, lifecycle outcome, temperature override, and score-adjustment actions at the service layer first.
5. Test transitions, protected states, scoring caps, score adjustments, and migration/backfill behavior.

### Phase 3 — Engagement integration

1. Refactor `ProspectOutreachTracker` to record immutable engagement events and delegate scoring/transitions.
2. Preserve existing delivery/link counters for display and compatibility.
3. Add meaningful revisit semantics and per-kind scoring caps/windows.
4. Add attributable report-visit tracking where a delivery/link token is available; do not track anonymous direct report visits as prospect intent.
5. Preserve first-hot queued notification semantics and test scanner/repeat-event safeguards.

### Phase 4 — Cold sequence

1. Add message types, delivery idempotency keys/status, and template snapshots.
2. Add the central due-evaluation command/job/service and scheduler entry.
3. Implement initial-sent → cold retry → final follow-up → exhausted/nurture decisions using configured delays and maximum attempts.
4. Pause advancement on meaningful engagement and enforce all stop states immediately before reservation and immediately before send.
5. Test overlapping evaluator runs, stale jobs, retries, failed sends, rescheduling, and exactly-once logical delivery reservation.

### Phase 5 — Hot lead and video workflow

1. Add the needs-personalised-video queue and reason summaries.
2. Add a dedicated form/action for URL, optional message edit, immediate/scheduled send, and video timestamp.
3. Track video links as their own link/event kind and transition to video sent only after successful delivery.
4. Ensure no path can automatically invent or send a personalised video.

### Phase 6 — Post-video behavior

1. Add the configurable, conversational post-video follow-up after the no-reply delay.
2. Allow only the configured single automatic post-video follow-up.
3. Convert continued high intent into a manual-follow-up recommendation with persisted reasons and no further aggressive automation.
4. Add manual and, if supported by the selected provider, automatic reply transitions.

### Phase 7 — Dashboard and timeline

1. Add daily priority counts and actionable queues to the admin UI.
2. Add recent-activity and reason summaries using eager-loaded/indexed queries.
3. Expand the prospect timeline with lifecycle, score, scheduling, cancellation, video, reply, pause, and manual-change events.
4. Add dedicated controls for every requested manual override with explicit confirmation where the action stops or resets automation.

### Phase 8 — End-to-end safeguards

1. Add state-table/dataset coverage for every lifecycle branch and protected outcome.
2. Add concurrent/duplicate evaluator and send tests around unique keys and locks.
3. Add mail, queue, scheduling, notification, signed tracking, reply, and dashboard tests.
4. Run the focused prospect suite, then the full suite and formatter.

## Decisions to confirm before later phases

- Which outbound/inbound email provider is used in production and whether it offers signed delivery/reply webhooks or inbound routing.
- Whether `future opportunity` should remain indefinitely paused until its date, then return to a manual queue or automatically re-enter a sequence. The safer default is a manual queue.
- Whether existing `converted` records should backfill to `customer`, while `pilot` remains a separate protected state.
- Whether pricing/Sitewell links should be included in outreach templates now or only tracked when manually included later.
- The preferred operating timezone for campaign rules. Existing scheduling explicitly uses Europe/London and stores UTC, which should remain the default.

## Verification notes

- Phase 1 inspection covered the relevant models, migrations/live MySQL schema, services, jobs, mail/view, controllers/requests/routes, scheduler, admin views, discovery/research flow, notifications, factories, and prospect feature tests.
- Live schema inspection confirmed the four core prospect/outreach tables and their indexes/constraints.
- No test run was required because Phase 1 changes documentation only.
