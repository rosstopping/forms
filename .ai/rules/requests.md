---
paths:
  - 'app/{Models/Website.php,Http/Controllers/Admin/**,Http/Requests/**}'
---

# Requests

## Use website membership authorization
Website access is owner-or-membership based. Use Website::accessibleTo() for scoped lists, isAccessibleBy() for read access, and isManageableBy() for mutations. Only the primary owner or a global admin may manage website members.

## Manager role supersedes owner-only member management
This supersedes the earlier owner-only member-management rule. Use Website::accessibleTo(), isAccessibleBy(), and isManageableBy(); both global admins and website managers may manage members, while viewers remain read-only.
