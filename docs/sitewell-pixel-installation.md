# Sitewell Pixel installation and operations

## Customer installation

Copy the installation snippet from the website's **Pixel / Connection** tab and add it before the closing `</head>` tag on every page:

```html
<script
    async
    src="https://cdn.sitewell.co.uk/pixel.js"
    data-site="SITE_PUBLIC_KEY"
    data-api="https://api.sitewell.co.uk/api/pixel"
></script>
```

The administration screen generates the authoritative snippet using the configured asset and API URLs. Do not copy this example verbatim.

The script is asynchronous, has no dependencies, and leaves the underlying page unchanged if Sitewell cannot be reached. The same DOM changes are applied for users and search-engine rendering; the runtime does not detect or treat crawlers differently.

## Content Security Policy

A restrictive policy may need the configured Pixel asset host added to `script-src` and the API host added to `connect-src`. For the example hosts above:

```text
script-src 'self' https://cdn.sitewell.co.uk
connect-src 'self' https://api.sitewell.co.uk
```

Merge these sources into the site's existing policy rather than replacing it. Sites that require nonces, hashes, or Subresource Integrity need a deployment-specific policy decision. Sitewell does not weaken or bypass customer CSP.

## Connection detection

The connection becomes detected only after all of the following occur:

1. A visitor loads a page containing the current snippet.
2. The browser successfully downloads `pixel.js`.
3. The public payload endpoint accepts the site key and page hostname.
4. The browser sends the throttled heartbeat after receiving a valid payload.

Heartbeats are limited to once per browser, normalized page, and day. The Pixel tab shows the last successful heartbeat and number of distinct detected pages; it is not an analytics counter.

If the tab still says **Not detected**:

- Confirm the snippet uses the current key shown in Sitewell, especially after key rotation.
- Confirm the tested hostname is registered on that Sitewell website. A similarly named or staging domain is rejected.
- Confirm Pixel is enabled in the connection tab.
- Open browser developer tools and verify `pixel.js`, the `GET /api/pixel/{siteKey}` request, and the heartbeat are not blocked by CSP, an ad blocker, consent manager, or firewall.
- Confirm the configured asset/API URLs are publicly reachable over HTTPS and do not redirect to authentication.
- Test in a fresh browser profile or clear the `sitewell-pixel-heartbeat:` local-storage entry when repeating a same-day heartbeat test.

## Production smoke check

After deployment, run the read-only operator check with a real site key and a URL registered to that website:

```shell
php artisan sitewell:pixel:check sw_PUBLIC_KEY https://www.example.com/services
```

The command verifies that the configured JavaScript asset is recognizable and that the payload endpoint is reachable, correctly shaped, CORS-enabled, and cacheable. Optional `--asset-url` and `--api-url` arguments can target a staging or CDN hostname.

Then perform the full browser smoke test:

1. Open the external page in a fresh browser profile and confirm the connection becomes detected.
2. Create a harmless test optimisation for that exact URL.
3. Approve and deploy it through Pixel.
4. Refresh the external page and inspect the rendered DOM, not only page source.
5. Confirm unrelated page behavior and console output remain normal.
6. Roll back the optimisation and refresh to confirm the origin content returns.
7. Verify the deployment and rollback entries remain in Sitewell history.

## Release checklist

- Run database migrations before enabling customer snippets.
- Configure `SITEWELL_PIXEL_ASSET_URL` and `SITEWELL_PIXEL_API_URL` with public HTTPS URLs.
- Ensure `public/pixel.js` is deployed at the configured asset URL.
- Enable gzip or Brotli and CDN caching for the static Pixel asset.
- Preserve the API's `ETag`, `Cache-Control`, and CORS response headers at the proxy/CDN.
- Confirm rate limiting and the application cache use production-capable stores.
- Run `npm run test:pixel`, `php artisan test --compact`, and `npm run build`.
- Run the production smoke command and browser workflow above.
- Start with an internal/test website before enabling customer sites.

## Emergency operations

- **Rollback** disables one deployed optimisation and retains its immutable history.
- **Rollback all on page** performs individual rollbacks for every live Pixel optimisation on that page.
- **Disable Pixel** immediately omits all Pixel optimisations for the site without changing their deployment status or history. Re-enabling restores the currently deployed set.
- **Rotate public key** invalidates the previous snippet and resets connection state. The customer must install the newly generated snippet.

Pixel rollback is non-destructive: it stops applying the DOM change, allowing the customer's original website content to appear again.
