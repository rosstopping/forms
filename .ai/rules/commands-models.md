---
paths:
  - 'app/Services/Jev*.php,app/Console/Commands/*Jev.php,app/Models/Jev*.php'
---

# Commands Models

## Keep Jev AI Visibility evaluation shadow-only
Jev evaluates saved completed AI Visibility responses only after explicit operator opt-in. Store requests, baseline decisions and typed answers in the separate shadow ledger; never update customer visibility, schedule provider observations or trigger OpenAI fallback. Keep Noul probability distinct from Choice confidence. Failed or interrupted reservations must not automatically replay paid requests.
