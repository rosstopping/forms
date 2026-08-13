---
paths:
  - 'app/{Services/OptimisationValueSanitizer.php,Services/PixelDeploymentDriver.php,Http/Requests/*Optimisation*.php,Http/Controllers/Admin/Optimisation*.php}'
---

# Controllers Admin

## Revalidate optimisation content at deployment
Validate and canonicalize every initial value and revision before storage, then re-run the same safety checks in PixelDeploymentDriver against the exact version being deployed. Reject scripts, active/embedded elements, event/style attributes, unsafe hrefs, unsupported writable attributes, external internal links, and invalid JSON-LD. Never rely on AI/internal provenance or client-side sanitization.
