---
paths:
  - 'app/{Models/PixelPageSighting.php,Services/PixelHeartbeatRecorder.php,Http/Controllers/PixelHeartbeatController.php}, public/pixel.js'
---

# Models Controllers

## Keep Pixel heartbeats non-analytical
Heartbeats record only the website last-seen summary and one deduplicated row per normalized page. The runtime reports at most once per browser/page/day after a valid payload. Do not add page-view events, counters, visitor identifiers, or high-frequency reporting; invalid keys/domains must remain a no-write 204.
