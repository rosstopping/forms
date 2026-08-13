---
paths:
  - 'app/{Services/Github*,Http/Controllers/**/*Github*,Models/{GithubInstallation,WebsiteRepository,RemediationRun}.php}'
  - 'app/{Services/PixelUrlNormalizer.php,Services/PixelPayloadBuilder.php,Http/Controllers/PixelPayloadController.php,Models/Optimisation.php}'
---

# Services Controllers

## Use short-lived GitHub App installation tokens
Persist GitHub installation IDs and repository metadata only. Generate installation access tokens just in time and never store them. Verify repository selections against the installation API and validate every webhook with X-Hub-Signature-256 before processing.

## Use one canonical Pixel URL identity
Pixel URL matching ignores HTTP/HTTPS, query strings, fragments, a leading www., and non-root trailing slashes; paths remain case-sensitive and unreserved percent encodings normalize. Always compare parsed normalized hostnames against WebsiteDomain and use the stored normalized URL hash—never prefixes, suffixes, or raw URL strings.
