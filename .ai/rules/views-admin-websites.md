---
paths:
  - 'app/{Ai/Agents/WebsiteDataAssistant.php,Services/WebsiteAiContext.php,Http/Controllers/Admin/WebsiteAiChatController.php,Models/WebsiteAiQuestion.php},resources/views/admin/websites/show.blade.php,routes/web.php'
---

# Views Admin Websites

## Scope and meter website data chat
The website data assistant is Complete-only and must receive only bounded data belonging to the selected accessible Website plus that user's recent same-website Q&A. It has no tools or browsing, treats prompts/context as untrusted, refuses unrelated questions, distinguishes Search Console measurements from SEO estimates, and atomically reserves each attempted request against the configured per-user/per-website calendar-week limit.
