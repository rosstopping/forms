---
paths:
  - 'app/{Http/Controllers/Admin/WebsiteMemberController,Http/Requests/{StoreWebsiteMemberRequest,UpdateWebsiteMemberRequest}}.php'
---

# Website Member Controller Http Requests

## Manage invitation access and package together
Website invitation and existing-member forms allow admins to choose an existing active account membership or grant a complimentary tier with optional inclusive end date. An explicit package choice assigns that member as the subscription account while preserving Viewer/Manager roles and previous implicit managers. Blank package means no membership or sponsorship change. Reject membership inputs from non-admin managers; do not mutate Stripe billing. Grant and attach membership in one transaction before sending the normal invitation.
