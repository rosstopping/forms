---
paths:
  - 'app/**'
---

# App

## Customer acknowledgement reply-to is independent of Postmark
Website acknowledgement defaults and nullable per-form overrides set Reply-To, never the fixed sender address. Snapshot the resolved Reply-To in queued acknowledgement jobs and delivery records, including retries. Saving acknowledgement defaults without an explicit mail_delivery_mode must leave the mail connection and its paused state unchanged.

## Separate managed service packages from SaaS permissions
Ross approved the managed-service transition on 8 October 2026; docs/managed-service-transition.md tracks rollout. Customers ultimately receive Overview, Leads (status/tags/notes) and Billing only; staff use one admin role with all-site or assigned-site access. Website service_package/service_status/service_ends_at are independent of account Stripe membership. Preserve billing IDs and paid-work opt-ins; migrate old scheduler/access consumers before removing legacy gates. Older Manager/Viewer/trial/owner-entitlement rules are superseded as each replacement phase lands.
