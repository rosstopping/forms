---
paths:
  - '{app/Services/WordPressStaticReleaseBuilder.php,app/Http/Controllers/WordPress*Release*Controller.php,wordpress-plugin/sitewell-by-digizu/**}'
---

# Sitewell By Digizu

## Reject unbuilt releases and serve static files before WordPress routing
Reject unbuilt front matter and server template directives before marking releases ready; incomplete Actions settings must never fall back to source. Artifact-configured sites must not be offered older source releases. Serve installed static files on early parse_request before SEO and robots handlers, retaining operational request bypasses including rest_route. Send no-store headers and DONOTCACHEPAGE; upstream caches and physical document-root files require rollout configuration. Rebuild the tracked wordpress-plugin/sitewell-by-digizu.zip whenever packaged plugin code changes.
