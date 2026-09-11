---
paths:
  - 'app/{Models/User,Http/Controllers/Admin/{WebsiteController,UserController},Http/Middleware/ResolveCurrentWebsite}.php'
---

# Middleware

## Keep subscription sponsorship separate from website permissions
Admins may select an existing website member as the subscription account using websites.user_id, including a Viewer; assignment must preserve explicit Viewer/Manager roles and materialize a former implicit manager before changing sponsorship. This admin-only subscription control supersedes the prohibition on exposing sponsorship, not the legacy Owner role. Admin-managed memberships support optional admin_membership_expires_at, inclusive of the date chosen in admin UI; expired overrides fall back to active Stripe entitlement. Include expiry in partial user/owner selects used for entitlement checks.
