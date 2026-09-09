---
paths:
  - 'app/Http/Controllers/Admin/ContentRequestController.php,tests/Feature/{ContentGenerationTest.php,AdminResourceEditingTest.php,SeoOpportunityContentRequestTest.php}'
  - 'app/Http/Controllers/Admin/UserController.php,tests/Feature/AdminResourceEditingTest.php'
---

# Admin Feature

## Keep manual content actions in Content
After adding or removing a manual content request, redirect explicitly to the selected website's Content section. Do not use the generic website show route because its default navigation may open the latest health report.

## Keep websites when deleting a user account
Administrator account deletion must retain the user's websites and website data, clearing websites.user_id via ON DELETE SET NULL and removing only that user's membership pivots via ON DELETE CASCADE. Sites may be left without a manager or subscription account; administrators retain management access. This account-deletion flow is an intentional exception to the last-manager safeguard for individual website membership removal. Preserve self-deletion and administrator authorization protections.
