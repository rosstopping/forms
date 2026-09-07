---
paths:
  - 'app/Http/Controllers/Admin/ContentRequestController.php,tests/Feature/{ContentGenerationTest.php,AdminResourceEditingTest.php,SeoOpportunityContentRequestTest.php}'
---

# Admin Feature

## Keep manual content actions in Content
After adding or removing a manual content request, redirect explicitly to the selected website's Content section. Do not use the generic website show route because its default navigation may open the latest health report.
