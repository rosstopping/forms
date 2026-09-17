---
paths:
  - '{app/Services/WordPressStaticReleaseBuilder.php,wordpress-plugin/sitewell-by-digizu/**}'
---

# Services Sitewell By Digizu

## Validate optimized artifacts and publish outside WordPress
All releases, including committed build output, require static-build-manifest.json v1 plus _headers; verify hashes and local HTML/CSS dependencies at packaging and installation. Keep EOT/OTF/XSL aliases and _headers. SITEWELL_STATIC_FRONTEND_PUBLIC_PATH enables atomic current symlink publication and persistent hashed assets; configure the tested Nginx static adapter before traffic cutover. PHP routing remains compatibility mode with no-store, including real 404s without 404.html. Direct hosting owns routing independently of the plugin checkbox; preserve explicit WordPress operational routes and never proxy static misses. Do not prune immutable assets with old releases. Run nginx-backed MISS/HIT regressions; ROWGLO_STATIC_FIXTURE adds the actual corpus.

## Managed direct delivery controls activation and rollback
Managed DirectDelivery supersedes the old checkbox-independent behavior; that rule now applies only to legacy SITEWELL_STATIC_FRONTEND_PUBLIC_PATH hosts. Require successful static-response probes before enabling; use the filesystem .enabled switch for disable/deactivation without a server reload, and never silently fall back to PHP in managed mode. Keep copied public snapshots outside the denied private release tree, retain hashed assets, serialize activation with installation and invalidate request-local option caches after locking. Run actual Apache/Nginx routing and rollback tests, optionally with ROWGLO_STATIC_FIXTURE; LiteSpeed still needs target-host staging verification.

## Default to uploads assets without host configuration
UploadsDelivery supersedes mandatory managed direct setup: normal enable prepares private rewritten HTML and publishes physical versioned asset URLs under WordPress uploads, while pages and operational requests stay in WordPress. Do not require Nginx edits, symlinks or DNS changes for the default mode. Retain original validated artifacts and old public asset directories; publish complete assets before switching rendered_path. Existing direct hosts migrate only on explicit save by removing their .enabled switch; legacy custom virtual hosts require separate migration. Test rewritten HTML/CSS dependencies and actual Rowglo upload URLs on a plain Nginx root.

## Accept compiled sites without optimizer metadata
Supersedes the mandatory optimized-artifact rule: static-build-manifest.json and _headers are optional. Validate supplied manifests, fingerprinted bytes, unbuilt templates and local dependencies as before; allow ordinary asset filenames without a manifest. Both Laravel packaging and plugin installation use the shared validator. Direct publication discovers fingerprinted assets from files without needing metadata; never treat ordinary filenames as immutable.

## Allow Google Fonts for hosted text fonts
Shared static validation permits HTTPS fonts.googleapis.com/css or /css2 query URLs and fonts.gstatic.com/s/ assets without fetching remote dependencies. This supersedes the blanket Google Fonts rejection. Keep rejecting other remote stylesheet/font dependencies, lookalike hosts and obsolete local WordPress Google font paths. Plugin 1.0.7 or later is required before deploying releases that use this exception.
