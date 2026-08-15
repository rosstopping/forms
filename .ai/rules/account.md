---
paths:
  - 'app/{Models/User.php,Support/MembershipPlan.php,Http/Middleware/EnsureMembershipFeature.php,Http/Controllers/{Account/BillingController.php,StripeWebhookController.php}},config/memberships.php,routes/web.php'
---

# Account

## Use owner subscription for package entitlements
Stripe is the subscription source of truth. Billing is account-level; website Growth and Complete entitlements come from the primary owner and shared members inherit them. Admins bypass gates for support. Only explicitly marketed Growth/Complete features are gated; unspecified features remain available across packages.
