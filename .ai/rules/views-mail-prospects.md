---
paths:
  - 'app/Services/Prospect*.php,resources/views/mail/prospects/**'
---

# Views Mail Prospects

## Unsubscribe permanently blocks outreach
Every live outreach message includes a signed unsubscribe link. GET only shows confirmation; POST records unsubscribed_at, suppresses sending, stops automation, cancels unsent deliveries and logs activity. Ordinary edits, resume, old engagement links and delayed video completion must not undo it. Test emails use signed preview links that cannot unsubscribe.
