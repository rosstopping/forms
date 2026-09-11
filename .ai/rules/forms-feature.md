---
paths:
  - 'app/{Services/FormSetupChecker.php,Http/Controllers/Admin/FormSetupCheckController.php},resources/views/admin/forms/show.blade.php,tests/Feature/FormSetupCheckTest.php'
---

# Forms Feature

## Form setup checks are configuration snapshots
Manual form setup checks require website management access and save only setup_check_results/setup_checked_at. Never invoke the submission endpoint, create leads, dispatch delivery jobs, or contact external services. Reuse the real domain/form/settings resolvers; distinguish configuration checks from live browser or delivery testing and show the last checked timestamp.
