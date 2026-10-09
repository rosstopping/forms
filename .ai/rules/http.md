---
paths:
  - 'app/Http/**'
---

# Http

## Restrict customers to the managed portal
Non-admin users, including impersonated customers, may only access Overview, Leads and Billing plus account/profile, website switching and return-to-admin utilities. RestrictCustomerWorkspace denies other admin routes regardless of historical subscription tiers or Manager/Viewer membership. Customer lead updates allow status, site-scoped tags and notes only; staff retain operational tools. Signed content-queue links also require authenticated admin access.
