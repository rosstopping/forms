---
paths:
  - 'app/{Http/Controllers/Admin/{WebsiteController.php,WebsiteMemberController.php},Models/Website.php},resources/views/admin/websites/show.blade.php,tests/Feature/{WebsiteMembershipTest.php,WebsiteCreationTest.php}'
---

# Feature

## Keep website settings simple and retain a manager
Website managers may rename a website, but customer settings must not expose domain changes or internal status/workspace diagnostics. Every membership mutation must leave at least one manager; hide sole-manager role/removal controls and enforce the same invariant transactionally on the server.
