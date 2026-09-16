---
paths:
  - '{app/Services/WordPressStaticReleaseBuilder.php,wordpress-plugin/sitewell-by-digizu/**}'
---

# Services Sitewell By Digizu

## Validate optimized artifacts and publish outside WordPress
All releases, including committed build output, require static-build-manifest.json v1 plus _headers; verify hashes and local HTML/CSS dependencies at packaging and installation. Keep EOT/OTF/XSL aliases and _headers. SITEWELL_STATIC_FRONTEND_PUBLIC_PATH enables atomic current symlink publication and persistent hashed assets; configure the tested Nginx static adapter before traffic cutover. PHP routing remains compatibility mode with no-store, including real 404s without 404.html. Direct hosting owns routing independently of the plugin checkbox; preserve explicit WordPress operational routes and never proxy static misses. Do not prune immutable assets with old releases. Run nginx-backed MISS/HIT regressions; ROWGLO_STATIC_FIXTURE adds the actual corpus.
