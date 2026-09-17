---
paths:
  - '{app/Http/Controllers/MarketingController.php,wordpress-plugin/sitewell-by-digizu/**}'
---

# Controllers Sitewell By Digizu

## Publish self-hosted plugin updates through WordPress
PluginUpdater uses Update URI and the native hostname update hook; leave auto-updates opt-in and never change frontend/connection settings during update checks. Public /wordpress/update.json derives version/requirements from the shipped ZIP and provides its checksum; pinned downloads reject obsolete versions. Restrict updater packages to Sitewell HTTPS and verify SHA-256 before native installation. Bump the version and rebuild/deploy the ZIP with the endpoint; keep current-version metadata so WordPress can expose auto-update controls.
