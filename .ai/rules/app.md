---
paths:
  - 'app/**'
---

# App

## Customer acknowledgement reply-to is independent of Postmark
Website acknowledgement defaults and nullable per-form overrides set Reply-To, never the fixed sender address. Snapshot the resolved Reply-To in queued acknowledgement jobs and delivery records, including retries. Saving acknowledgement defaults without an explicit mail_delivery_mode must leave the mail connection and its paused state unchanged.
