---
paths:
  - '{app/Http/Controllers/FormSubmissionController.php,app/Services/Turnstile*.php}'
---

# Controllers Services

## Separate form verification failures from spam verdicts
Turnstile host matching follows SourceWebsiteResolver: remove one leading www and decode IDN, then require the exact verified domain on the same website; never trust arbitrary subdomains or pending domains. Verification failures return explicit 422 errors; provider/configuration/connection failures return retryable 503 without saving or delivering a lead. Only content/honeypot evidence is silently quarantined. Record fixed reason codes and IDs only in verification logs and spam reasons in created activity metadata; never log tokens, secrets, IPs or message content. Do not retry single-use tokens automatically or replay old enquiries.
