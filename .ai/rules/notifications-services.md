---
paths:
  - 'app/{Notifications,Services}/**/*Prospect*.php'
---

# Notifications Services

## Notify admins once when prospects become hot
When a verified tracked click transitions a prospect from cold/warm to hot, queue one email notification to every Sitewell admin after the transaction. Include the clicked link label and an authenticated link to the prospect. Lock the prospect during transition and do not notify again for later clicks while it remains hot.
