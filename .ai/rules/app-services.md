---
paths:
  - 'app/{Services/Github*,Models/GithubUserAuthorization.php,Jobs/{StartCopilotRemediation,SyncCopilotRemediation}.php}'
---

# App Services

## Encrypt and rotate GitHub user authorizations
Copilot Agent Tasks require a GitHub App user token, not an installation token. Store access and refresh tokens only with encrypted casts, hide them from serialization, refresh shortly before expiry under an atomic lock, and never put tokens into queued job payloads or logs.
