---
paths:
  - app/Http/Controllers/Admin/ProspectController.php
---

# Http Controllers Admin

## Keep sorted prospect list rows small
The prospect index must select only fields needed for list rendering before sorting and paginating. Do not SELECT * in that query: research JSON and email bodies can exhaust MySQL filesort memory. Preserve temperature priority and use created_at plus id for stable pagination.
