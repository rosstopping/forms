---
paths:
  - '{resources/views/marketing/home.blade.php,resources/js/home-reveal.js,resources/css/app.css}'
---

# Js Css

## Keep homepage scroll motion subtle and accessible
The homepage uses a small native IntersectionObserver reveal for search marks, service details, the walkthrough, and founder content. Keep the hero audit form visible immediately. Reveals run once, use a short fade or small upward motion, respect prefers-reduced-motion, and leave content visible when JavaScript or IntersectionObserver is unavailable. Avoid adding an animation dependency for this effect.
