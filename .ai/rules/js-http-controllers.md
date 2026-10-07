---
paths:
  - '{resources/js/cal-booking.js,resources/js/marketing-events.js,app/Http/Controllers/PpcLandingController.php}'
---

# Js Http Controllers

## Audit call button opens the calendar in a popup
The public audit call button uses the Cal.com popup embed, loading the SDK on click. Fetch the existing booking route as JSON to retain metadata.sitewell_booking attribution; only the authenticated Cal webhook confirms a booking. Keep its href for normal/modified clicks and fall back to the booking URL when the SDK or request fails. Do not treat opening the modal as a lead conversion.
