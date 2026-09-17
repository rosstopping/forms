---
paths:
  - '{app/Http/Controllers/GithubWebhookController.php,app/Models/WebsiteRepository.php,database/migrations/*website_repositories*.php}'
---

# Models Migrations

## Repositories may be shared across websites
Keep one repository connection per website, but allow the same GitHub installation/repository pair on multiple websites. Preserve an ordinary composite index for the installation foreign key when removing global uniqueness. GitHub events must consider every matching website connection; releases, settings and disconnects remain website-scoped.
