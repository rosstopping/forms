---
paths:
  - app/Http/Requests/StoreFreeSiteAuditRequest.php
---

# App Http Requests

## Defer local macOS audit DNS checks to the guarded queue worker
Herd PHP-FPM on macOS can hang in dns_get_record until the request dies with a 502, even when CLI DNS succeeds. Skip the submission-time DNS lookup only for APP_ENV=local on Darwin (and the existing unit-test bypass). Keep URL/host/credential/port validation and production DNS validation intact. ProspectWebsiteAnalyzer still resolves and rejects non-public addresses before each fetch/redirect in the queued audit; never bypass that guard.
