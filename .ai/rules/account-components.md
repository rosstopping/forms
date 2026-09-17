---
paths:
  - 'resources/{css/app.css,js/app.js,views/admin/**,views/account/**,views/components/**}'
---

# Account Components

## Share app surfaces and controls through ui utilities
Use ui-panel with ui-section for padded standalone sections, ui-well for inset content, ui-input/ui-label for fields, ui-button plus primary/secondary/danger variants for actions, and ui-tabs/ui-tab with aria-current for subsection links. Definitions live in app.css. Keep layout utilities local but avoid duplicating border/radius/padding/colour defaults. Confirmation JS switches ui-button-primary and ui-button-danger-confirm; preserve semantic warnings, status colours and dark code previews.
