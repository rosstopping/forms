# Customer Outreach Product Plan

## Goal

Turn the existing owner-operated outreach tooling into a safe multi-tenant product for Growth-plan customers, potentially sold as an add-on. Customers should be able to find and qualify relevant leads, create evidence-based outreach drafts, and eventually send approved outreach from their own domains.

The product must not assume every customer is selling Sitewell. Website research should produce reusable evidence, while each customer's sales strategy determines qualification, positioning, copy, and calls to action.

## Product boundaries

Treat the product as separate modules:

1. Lead discovery.
2. Contact discovery and provenance.
3. Website research and optional audit evidence.
4. Customer-specific qualification and scoring.
5. Outreach draft generation and approval.
6. Customer-domain email delivery and follow-up automation.
7. Engagement tracking and pipeline management.

A safe initial release should provide discovery, qualification, contact finding, website evidence, and drafts without email delivery. Sending and sequences can follow as a separately controlled add-on.

## Customer configuration

Each workspace will need its own:

- Ideal customer profile, industries, locations, and exclusions.
- Qualification criteria and scoring weights.
- Services, value proposition, approved claims, and offers.
- Tone of voice, sender identity, signature, and calls to action.
- Search limits, crawl depth, and optional audit checks.
- Sending schedule, volume limits, and follow-up policy.

Research evidence, customer sales strategy, and delivery must remain separate concerns. Generated copy must distinguish verified evidence from inference and must never invent claims.

## Multi-tenancy and permissions

All searches, candidates, prospects, contact details, findings, drafts, campaigns, deliveries, engagement events, suppression entries, sender credentials, and usage records must belong to a workspace.

Every query, queued job, signed URL, export, webhook, and download must enforce tenant ownership. Team permissions should distinguish account ownership, strategy management, draft editing, approval, sending, exporting, and suppression management.

## Customer-domain sending

Customers should send through either:

- Their Google Workspace or Microsoft 365 mailbox connected with OAuth.
- A verified customer domain configured through the platform's delivery provider.
- A supported bring-your-own-provider connection for advanced customers.

The platform must verify and monitor SPF, DKIM, DMARC, sender alignment, reply-to configuration, domain ownership, mailbox health, bounces, and complaints. Customer outreach must never use Sitewell's primary sending domain.

Replies should initially remain in the customer's mailbox. The first integration only needs to detect replies, update the prospect, and stop automation; a shared inbox can remain a later feature.

## Deliverability safeguards

- Explicit approval before the first live send.
- Conservative limits for new workspaces, domains, and mailboxes.
- Per-mailbox and per-domain daily limits.
- Gradual limit increases based on delivery history.
- Recipient-time-zone scheduling and quiet hours.
- Duplicate-recipient and duplicate-delivery protection.
- Bounce and complaint webhooks with automatic suppression.
- Automatic campaign pauses when safety thresholds are exceeded.
- Sequence cancellation after replies, objections, bounces, complaints, suppression, or protected lifecycle changes.
- Clear separation between test messages and tracked live outreach.

## Compliance and privacy

Obtain specialist legal advice before enabling customer sending across jurisdictions. At minimum, the product needs:

- Geographic and recipient-type restrictions.
- A recorded lawful basis or customer compliance declaration.
- Support for legitimate-interest assessments where applicable.
- Contact source, collection date, and business/entity type where known.
- Privacy-notice and right-to-object handling.
- A visible unsubscribe or objection mechanism.
- Immediate and durable suppression across imports and campaigns.
- Data export, correction, deletion, and retention workflows.
- An audit log showing who imported, edited, approved, scheduled, and sent outreach.
- A Data Processing Agreement, subprocessor list, retention schedule, acceptable-use policy, and incident procedure.

Lead sources must be reviewed for their terms, API licences, robots policies, database rights, and restrictions on storing or redistributing results. Public availability alone must not be treated as permission to build a reusable contact database.

## Lead provenance

Each lead should retain:

- Source provider and source URL.
- Discovery and collection timestamps.
- Search, customer, and workspace attribution.
- Original website and contact source.
- Generic versus personally identifying address classification.
- Business entity type where known.
- Qualification evidence and score breakdown.
- Import, exclusion, and deduplication decisions.

This information should be visible enough for customers to understand why a lead was selected and what statements can safely be used in outreach.

## Abuse prevention

- Verify customer identity, billing method, company, and sending domain.
- Apply manual review to early or higher-risk accounts.
- Prohibit unlawful lists, phishing, impersonation, deceptive claims, and restricted industries.
- Rate-limit searches, imports, exports, drafts, and sends.
- Detect unusual volumes, complaint rates, and repeated policy violations.
- Give platform administrators tools to inspect, pause, suspend, and permanently suppress activity.
- Preserve the minimum suppression evidence needed to prevent re-contact after account deletion.

## Metering and commercial model

Meter variable-cost operations independently:

- Keyword/search operations.
- Candidates analysed.
- Contacts discovered or enriched.
- Pages crawled.
- AI drafts generated.
- Emails sent.
- Connected mailboxes, team seats, and retention.

Show estimated usage before expensive searches and reserve allowance atomically before dispatching queued work.

A likely packaging model is:

- Growth plan: a limited monthly discovery and drafting allowance.
- Outreach add-on: higher allowances, customer-domain sending, sequences, and engagement tracking.
- Additional usage: prepaid credits or capped overages.

## Delivery phases

### Phase 1 — Multi-tenant research and drafts

- Workspace ownership and permissions.
- Customer-specific prospecting strategy.
- Lead and contact provenance.
- Configurable website evidence and qualification.
- Evidence-based drafts with explicit approval.
- Usage metering, plan limits, and admin oversight.
- No customer email sending.

### Phase 2 — Controlled customer-domain sending

- OAuth mailbox or verified-domain onboarding.
- SPF, DKIM, and DMARC checks.
- Bounce, complaint, unsubscribe, and suppression handling.
- Strict sending limits and approval boundaries.
- Delivery snapshots, idempotency, and audit logging.
- Reply detection that stops automation.

### Phase 3 — Sequences and team workflows

- Bounded follow-up sequences.
- Engagement scoring and lifecycle automation.
- Assignment, team roles, and review queues.
- CRM integrations and optional shared-inbox functionality.
- Mature abuse detection and deliverability reporting.

## Non-negotiable constraints

- No automatic sending merely because a lead was found or analysed.
- No cross-workspace visibility or shared suppression leakage.
- No invented audit findings or customer claims.
- No sending from Sitewell's primary domain.
- No bypass of suppression, approval, idempotency, or protected lifecycle states.
- No unlimited discovery or sending without cost and abuse controls.
