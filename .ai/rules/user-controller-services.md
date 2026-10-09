---
paths:
  - 'app/{Models/User,Models/Website,Http/Middleware/RestrictAssignedAdmin,Http/Controllers/Admin/UserController,Services/WebsiteMailRecipients}.php'
---

# User Controller Services

## Enforce assigned admin access independently of customer membership
Admins use admin_site_access=all or assigned with staff_website assignments; empty assigned scope grants no sites, and customer ownership/membership never extends staff scope. Existing admins retain all access during migration. Only all-site admins manage users, impersonation, service packages, billing ownership and global tools; protect the final all-site admin. Scope lists, nested HTTP actions, OAuth callbacks, signed queue links and report recipients. isAdmin remains a role/feature check, not authorization for arbitrary websites.
