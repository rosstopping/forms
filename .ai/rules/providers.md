---
paths:
  - app/Providers/AppServiceProvider.php
---

# Providers

## Keep local audit development free of 429s
The public website audit submission, report view, and status polling limiters are unlimited only when APP_ENV=local, so repeated manual audits and open polling tabs do not hit 429 during development. Keep the production IP/domain limits and Turnstile protection unchanged; route report and status requests through the named limiters.
