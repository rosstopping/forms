---
paths:
  - 'app/{Models/ContentRequest.php,Jobs/StartContentGeneration.php,Http/Controllers/Admin/{ContentRequestController.php,ContentRequestPixelController.php}},resources/views/admin/websites/show.blade.php'
---

# Models Controllers Admin Views Admin Websites

## Use one content queue order
Pending content requests use ContentRequest::pendingInQueueOrder(): most recently bumped requests first, then unbumped requests FIFO with ID tie-breaking. Copilot pickup, Pixel batching, and the Content UI must use this same order. Bumping changes bumped_at only, never created_at, and is restricted to pending requests manageable through their website.
