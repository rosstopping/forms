---
paths:
  - 'app/{Models/Website.php,Http/Controllers/Admin/**,Http/Requests/**}'
---

# Requests

## Use website membership authorization
Website access is owner-or-membership based. Use Website::accessibleTo() for scoped lists, isAccessibleBy() for read access, and isManageableBy() for mutations. Only the primary owner or a global admin may manage website members.
