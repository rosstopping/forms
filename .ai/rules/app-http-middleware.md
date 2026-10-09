---
paths:
  - app/Http/Middleware/RestrictCustomerWorkspace.php
---

# App Http Middleware

## Customers may read top-level search performance
Customers may open the dedicated search-overview route for accessible websites; legacy website Search section links redirect to it. Show stored summary comparisons and property-scoped monthly charts only, with no live API calls, detail/query tables, opportunities or connection controls. Search switching preserves this route. Customer Overview puts Weekly Overview before a collapsed progress timeline.
