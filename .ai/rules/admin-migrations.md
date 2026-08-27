---
paths:
  - 'app/Http/{Controllers/Admin/FormController.php,Requests/UpdateWebsiteAutoresponderRequest.php},database/migrations/**/*autoresponder*.php'
---

# Admin Migrations

## Allow full email templates in autoresponder bodies
Autoresponder body fields must support complete email documents: validate up to 1,000,000 characters and store website and form body columns as LONGTEXT. Keep validation failures visible next to both editors.
