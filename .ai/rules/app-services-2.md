---
paths:
  - '{app/Services/MarketingAuditScreenshot.php,package.json,.npmrc}'
---

# App Services 2

## Provision the matching screenshot browser explicitly on Forge
Production logs on 7 October 2026 confirm Browsershot 5.4 launches Puppeteer with headless: shell and needs chrome-headless-shell, not only regular Chrome. This repository keeps npm ignore-scripts=true; installs do not download the browser automatically. Run npx puppeteer browsers install chrome-headless-shell explicitly after npm installation as the queue worker user (forge), using its persistent /home/forge/.cache/puppeteer cache. Ensure Linux runtime dependencies and the current Puppeteer-pinned version are installed; keep install-script protections and public-host/redirect guards. Capture failures are reported but intentionally do not fail or retry the completed audit. Existing audit previews can be recaptured through MarketingAuditScreenshot alone without rerunning paid research or sending email.
