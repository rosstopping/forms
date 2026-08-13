---
paths:
  - public/pixel.js
---

# Public

## Keep the Pixel inert and failure-isolated
The public Pixel must remain dependency-free, non-blocking, and silent on every failure. Apply each optimisation in its own try/catch; never execute arbitrary JavaScript. Sanitize HTML with element/attribute allow-lists, reject active URL schemes/event attributes, and only create script elements for parsed JSON-LD.
