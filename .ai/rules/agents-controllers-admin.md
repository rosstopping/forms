---
paths:
  - 'app/{Ai/Agents/PixelOptimisationWriter.php,Services/PixelOptimisationGenerator.php,Http/Controllers/Admin/*PageOptimisationsController.php}'
---

# Agents Controllers Admin

## Automate Pixel drafting but keep one approval boundary
AI should generate structured Pixel drafts from verified crawl evidence so users do not enter types/selectors/values manually. Treat AI output as untrusted: allow-list, length-check, sanitize, reject duplicates/unchanged values, and never guess selectors. A single explicit Approve & deploy all action remains the publication boundary.
