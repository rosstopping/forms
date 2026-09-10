# Roadmap

## Near term

- Close the advertised-feature gaps below, starting with plan alignment and lead follow-up foundations.
- Ship Search Console opportunity alerts for ranking gaps, weak click-through rates, declining pages, emerging queries, and query cannibalisation. Opportunities can be queued into the existing approval-first content workflow.
- Add PageSpeed and Core Web Vitals monitoring for important pages on mobile and desktop.
- Ship structured-data validation and evidence-based schema opportunity reporting through weekly health reports and approval-first automated remediation.
- Add safe form heartbeat monitoring that validates form resolution, CORS, validation, mail, and webhook configuration without creating a lead or contacting customers.
- Add invite-based sharing so a user can grant access to another account without handing over the full admin login.
- Introduce per-user API keys for webhook and CRM integrations.
- Add richer inbox-style filtering for submissions, including spam scoring and labels.

## Medium term

- Add website change monitoring for titles, descriptions, canonicals, indexing directives, headings, structured data, forms, status codes, redirects, and significant content changes (deferred).
- Pass detailed Lighthouse recommendations, lab measurements, estimated savings, and affected URLs into approval-first automated remediation prompts (deferred).
- Add SSL certificate, HTTPS, mixed-content, DNS, and domain-expiry monitoring where registry data is available.
- Add uptime and response-time monitoring with consecutive-failure thresholds to avoid noisy alerts.
- Add a customer-friendly monthly report covering health, search, content, forms, completed work, and pending approvals.
- Extend the existing lead inbox with tags and shared customer conversations; see the all-in-one conversation inbox item below.
- Add AI summaries and suggested follow-up actions for new submissions.
- Support file uploads, multi-step forms, and appointment scheduling.

## Longer term

- Connect to CRM tools and payment flows.
- Add analytics dashboards. Review request automation is tracked in the advertised-feature backlog below.
- Expand into quoting, invoicing, and advanced workflow automation.
- Offer the outreach tooling to Growth-plan customers, potentially as a separately billed add-on. See `docs/customer-outreach-product-plan.md`.

## Advertised-feature backlog

Captured from the Digizu and Sitewell marketing review on 10 September 2026. These are open product gaps, not delivery commitments or agreed plan entitlements. Digizu's [former pay-monthly page](https://digizu.co.uk/pay-monthly-websites/) retires the old packages but still links to these services; several describe a setup to discuss rather than inclusion in every Sitewell plan. Existing agency delivery through external tools should be confirmed when scoping each item.

### Plan alignment and lead follow-up foundations

- [ ] **Align Essential customer replies with the advertised plan.** [Sitewell pricing](https://sitewell.digizu.co.uk/pricing) includes customer replies in Essential, while automatic acknowledgements are gated to Growth and Complete. Decide the intended entitlement, align the copy and runtime checks, and verify acknowledgement availability for each plan. The acknowledgement feature already exists; this is a plan mismatch.
- [ ] **Send follow-up reminder notifications.** [Sitewell features](https://sitewell.digizu.co.uk/features) advertises reminders. Follow-up dates, due/overdue filters and a navigation count already exist. Add scheduled notifications to the responsible team member, with delivery history and duplicate prevention. Completion requires reminders to arrive without opening Sitewell, and rescheduled, cleared or closed leads to stop obsolete reminders. Choose supported notification channels during scoping.
- [ ] **Build automated lead follow-up for clients' enquiries.** [Digizu claim](https://digizu.co.uk/features/automated-lead-follow-up/). Extend the existing one-off acknowledgement with configurable message sequences, timing, start/pause/stop conditions, reply detection and human handover. Completion requires a lead to progress through a sequence, stop on a reply or closure, and expose message and delivery history. Sitewell's own prospect outreach is not this customer feature; scope it separately from the longer-term customer outreach product.

### Customer conversations

- [ ] **Build missed-call text back.** [Digizu claim](https://digizu.co.uk/features/missed-call-text-back/). Add a supported phone-provider connection, missed-call detection, configurable SMS acknowledgement and incoming reply capture. Completion requires a verified missed call to trigger one acknowledgement, its reply to reach the responsible team, and repeated provider events to avoid duplicate texts. Confirm number compatibility, routing, delivery failures, opt-outs, usage costs and plan scope before rollout.
- [ ] **Build an all-in-one conversation inbox.** [Digizu claim](https://digizu.co.uk/features/all-in-one-inbox/). Extend the existing form-submission inbox, assignments, notes and statuses with customer conversation threads, an outbound reply composer and inbound message syncing. Select and explicitly document supported channels; do not imply every messaging service is connected. Completion requires staff to read, reply, receive a response and hand over a conversation within the correct website workspace. Provide the reply events needed by automated follow-up and missed-call workflows.

### Reviews and repeat business

- [ ] **Add customer review request automation.** [Digizu claim](https://digizu.co.uk/features/five-star-review-scaler/). Google review monitoring and replies already exist. Add a completed-work trigger or manual invitation, a direct review link, configurable reminders and invitation history. Completion requires a request to be sent and tracked, repeat requests to be controlled, and reminders to stop on opt-out or the configured end condition. Invite honest feedback consistently without filtering customers by expected rating.
- [ ] **Add customer marketing campaigns.** [Digizu claim](https://digizu.co.uk/features/one-click-marketing/). Add website-scoped customer contact lists, audience segmentation, a campaign composer, preview/review, scheduling and delivery reporting for seasonal reminders, relevant updates and repeat-business campaigns. Completion requires a reviewed campaign to reach only its selected eligible audience, respect unsubscribes and show sending outcomes. Keep this separate from Sitewell's own prospect acquisition sequences.

### Complete plan scope

- [ ] **Define and deliver advanced lead handling on Complete.** [Sitewell pricing](https://sitewell.digizu.co.uk/pricing) advertises this without a distinct implemented Complete-only lead workflow. Decide whether it means additional product capabilities, a managed service, or both; specify the deliverables and plan boundaries. Completion requires concrete customer-visible value beyond the existing CRM, matching marketing copy, and entitlement checks for any exclusive product capabilities. Dedicated support and specialist delivery need operational confirmation, not just code.
