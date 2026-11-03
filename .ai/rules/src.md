---
paths:
  - 'wordpress-plugin/sitewell-by-digizu/src/**'
---

# Src

## Reject overlapping updates before network work
Webhook, cron and manual update checks must share a nonblocking update lock before heartbeat/discovery/download, with a separate nonblocking installer lock for delivery migrations. Return HTTP 202 for an update already in progress so retries do not consume PHP workers. Release handles in finally; never unlink a live lock file or alter the running release on contention.
