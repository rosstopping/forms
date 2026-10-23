---
paths:
  - '{app/Services/Agent*.php,app/Services/SitewellAgentClient.php,app/Http/{Controllers/AgentWebsiteController.php,Requests/ReadAgentEvidenceRequest.php},app/Models/WebsiteLocalProject.php,app/Console/Commands/{ReadAgentEvidence,LinkAgentProject}.php}'
---

# Models Console Commands

## Expose stored SEO evidence through a scoped read-only agent API
Agent evidence reads require the dedicated bearer principal and current Website::accessibleTo access, including parent report/audit/impact scoping and current-property Search Console hashes. Never fetch provider data or start work while reading; paginate narrow IDs before loading large JSON, allowlist fields, bound nested payloads and return provenance, dates, filter support and coverage caveats. Project links are host-specific metadata recorded against an exact verified domain; never execute returned paths or infer publishing authority from a mapping. The CLI uses the configured HTTPS origin only, with no redirects or credential/error-body output.
