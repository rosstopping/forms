# Roadmap

## Near term

- Close the advertised-feature gaps below, starting with plan alignment and lead follow-up foundations.
- Ship Search Console opportunity alerts for ranking gaps, weak click-through rates, declining pages, emerging queries, and query cannibalisation. Opportunities can be queued into the existing approval-first content workflow.
- Add PageSpeed and Core Web Vitals monitoring for important pages on mobile and desktop.
- Ship structured-data validation and evidence-based schema opportunity reporting through weekly health reports and approval-first automated remediation.
- Add safe form heartbeat monitoring that validates form resolution, CORS, validation, mail, and webhook configuration without creating a lead or contacting customers.
- Add invite-based sharing so a user can grant access to another account without handing over the full admin login.
- Introduce per-user API keys for webhook and CRM integrations.
- Lead tags and tag filtering shipped 10 September 2026: website-specific reusable tags, lead assignment/removal, activity history, and filtering that also scopes bulk actions. Richer spam-score filtering remains open.

## Medium term

- Add website change monitoring for titles, descriptions, canonicals, indexing directives, headings, structured data, forms, status codes, redirects, and significant content changes (deferred).
- Pass detailed Lighthouse recommendations, lab measurements, estimated savings, and affected URLs into approval-first automated remediation prompts (deferred).
- Add SSL certificate, HTTPS, mixed-content, DNS, and domain-expiry monitoring where registry data is available.
- Add uptime and response-time monitoring with consecutive-failure thresholds to avoid noisy alerts.
- Add a customer-friendly monthly report covering health, search, content, forms, completed work, and pending approvals.
- Extend the existing tagged lead inbox with shared customer conversations; see the all-in-one conversation inbox item below.
- Add AI summaries and suggested follow-up actions for new submissions.
- Support file uploads, multi-step forms, and appointment scheduling.

## Longer term

- Connect to CRM tools and payment flows.
- Add analytics dashboards. Review request automation is tracked in the advertised-feature backlog below.
- Expand into quoting, invoicing, and advanced workflow automation.
- Offer the outreach tooling to Growth-plan customers, potentially as a separately billed add-on. See `docs/customer-outreach-product-plan.md`.

## Advertised-feature backlog

Captured from the Digizu and Sitewell marketing review on 10 September 2026. Unchecked items are open product gaps, not delivery commitments or agreed plan entitlements. Digizu's [former pay-monthly page](https://digizu.co.uk/pay-monthly-websites/) retires the old packages but still links to these services; several describe a setup to discuss rather than inclusion in every Sitewell plan. Existing agency delivery through external tools should be confirmed when scoping each item.

### Plan alignment and lead follow-up foundations

- [x] **Align Essential customer replies with the advertised plan.** Completed 10 September 2026. Active Essential, Growth and Complete plans can configure and send automatic acknowledgements, matching [Sitewell pricing](https://sitewell.digizu.co.uk/pricing). Existing membership checks, sender configuration and delivery controls remain in place; replies are not enabled automatically and old submissions are not replayed.
- [x] **Send follow-up reminder notifications.** Completed 10 September 2026. Setting or changing a lead’s follow-up date schedules one internal email when due, to an eligible managing assignee or otherwise the eligible website owner. Existing dates are not backfilled. Clearing/rescheduling cancels pending reminders; sending rechecks lead status, spam, website activity, membership and recipient access. Unique queued jobs and stored reminder state prevent routine duplicates, retry delivery failures and record sent/cancelled/failed activity. Dispatch runs every minute through `leads:dispatch-follow-up-reminders`. Email only; customer follow-up sequences remain a separate item below.
- [ ] **Build automated lead follow-up for clients' enquiries.** [Digizu claim](https://digizu.co.uk/features/automated-lead-follow-up/). Extend the existing one-off acknowledgement with configurable message sequences, timing, start/pause/stop conditions, reply detection and human handover. Completion requires a lead to progress through a sequence, stop on a reply or closure, and expose message and delivery history. Sitewell's own prospect outreach is not this customer feature; scope it separately from the longer-term customer outreach product.

### Customer conversations

- [ ] **Build missed-call text back.** [Digizu claim](https://digizu.co.uk/features/missed-call-text-back/). Add a supported phone-provider connection, missed-call detection, configurable SMS acknowledgement and incoming reply capture. Completion requires a verified missed call to trigger one acknowledgement, its reply to reach the responsible team, and repeated provider events to avoid duplicate texts. Confirm number compatibility, routing, delivery failures, opt-outs, usage costs and plan scope before rollout.
- [ ] **Build an all-in-one conversation inbox.** [Digizu claim](https://digizu.co.uk/features/all-in-one-inbox/). Extend the existing form-submission inbox, assignments, notes and statuses with customer conversation threads, an outbound reply composer and inbound message syncing. Select and explicitly document supported channels; do not imply every messaging service is connected. Completion requires staff to read, reply, receive a response and hand over a conversation within the correct website workspace. Provide the reply events needed by automated follow-up and missed-call workflows.

### Reviews and repeat business

- [x] **Send manual customer review invitations.** Completed 10 September 2026. Added a distinct Work completed lead status, available in individual updates, bulk actions and inbox filtering. Website managers can configure a shared HTTPS review link, preview the customer email and explicitly queue one invitation per completed lead. Stored recipient/message/link snapshots, sending timestamps and activity history show queued, sent, failed or cancelled outcomes. Sending rechecks permissions, spam, website/membership status and changed addresses/links. Existing Won leads are not marked completed automatically; there are no automatic sends or reminders.
- [ ] **Extend customer review request automation.** [Digizu claim](https://digizu.co.uk/features/five-star-review-scaler/). Google review monitoring and replies already exist. Manual invitations, review links and invitation history are implemented above. Add configurable reminders and any opt-in automatic completed-work trigger. Completion requires a request to be sent and tracked, repeat requests to be controlled, and reminders to stop on opt-out or the configured end condition. Invite honest feedback consistently without filtering customers by expected rating.
- [ ] **Add customer marketing campaigns.** [Digizu claim](https://digizu.co.uk/features/one-click-marketing/). Add website-scoped customer contact lists, audience segmentation, a campaign composer, preview/review, scheduling and delivery reporting for seasonal reminders, relevant updates and repeat-business campaigns. Completion requires a reviewed campaign to reach only its selected eligible audience, respect unsubscribes and show sending outcomes. Keep this separate from Sitewell's own prospect acquisition sequences.

### Complete plan scope

- [ ] **Define and deliver advanced lead handling on Complete.** [Sitewell pricing](https://sitewell.digizu.co.uk/pricing) advertises this without a distinct implemented Complete-only lead workflow. Decide whether it means additional product capabilities, a managed service, or both; specify the deliverables and plan boundaries. Completion requires concrete customer-visible value beyond the existing CRM, matching marketing copy, and entitlement checks for any exclusive product capabilities. Dedicated support and specialist delivery need operational confirmation, not just code.
