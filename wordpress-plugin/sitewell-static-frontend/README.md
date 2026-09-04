# Sitewell Static Frontend

This proof-of-concept WordPress plugin replaces the public theme with a bundled static site while leaving WordPress administration available. Static mode defaults to disabled and can be switched off immediately to restore the original frontend. It can also pair with an existing Sitewell website using a one-time connection code.

This directory is independently packageable and has no runtime dependency on the surrounding Laravel application.

## Current scope

- Serves clean static routes such as `/`, `/services/`, and `/services/boiler-installation/`.
- Serves a virtual `/new-service/` route without a corresponding WordPress page.
- Serves allow-listed static assets through the same safe resolver.
- Returns the bundled `404.html` document with HTTP status 404 for unknown routes.
- Bypasses WordPress administration, login, REST, AJAX, cron, XML-RPC, comments, WP-CLI, and non-read requests.
- Rejects traversal, dotfiles, null bytes, backslashes, symlink escapes, executable files, and unknown extensions.
- Disables WordPress canonical redirects for valid static routes.
- Connects to Sitewell using a short-lived code generated from a website's Content tab.
- Stores only the resulting revocable installation credential; GitHub credentials never enter WordPress.
- Tests the authenticated connection and can revoke it from either WordPress or Sitewell.

Remote releases, ZIP uploads, and automatic updates are intentionally outside this milestone.

## Installation

Build the installable archive:

```bash
composer package
```

Alternatively, run `sh bin/package.sh`. Upload `build/sitewell-static-frontend.zip` through WordPress Plugins → Add New, then activate it.

The plugin remains disabled after activation. Go to Settings → Sitewell Static Frontend and select **Enable static frontend**.

## Connect to Sitewell

1. Connect the website's source repository in Sitewell.
2. Open the website's Content tab and select **Generate connection code**.
3. In WordPress, open Settings → Sitewell Static Frontend.
4. Enter the one-time code and select **Connect to Sitewell** within ten minutes.
5. Use **Test connection** to send an authenticated heartbeat.

The WordPress hostname must match a domain already assigned to the selected Sitewell website. Production connections require HTTPS. Define `SITEWELL_STATIC_FRONTEND_API_URL` in `wp-config.php` before activating the plugin only when a non-production Sitewell API is required for development.

## Architecture

`BundledStaticRootProvider` supplies the current fixture directory. A future provider can point at an active, versioned Sitewell release without changing the path resolver or router.

`StaticPathResolver` converts a request URI into an allow-listed, readable file and verifies its canonical path remains inside the static root. `BypassPolicy` keeps operational WordPress requests away from the static router. `FrontendRouter` selects the static router template and streams the resolved file with the appropriate status and content type.

Static files are read with `readfile`; they are never included or evaluated as PHP.

## Development

Install development dependencies inside this plugin directory:

```bash
composer install
composer test
composer lint
```

From the parent Sitewell repository, the unit tests can also use its existing PHPUnit installation:

```bash
vendor/bin/phpunit --no-configuration \
  --bootstrap wordpress-plugin/sitewell-static-frontend/tests/bootstrap.php \
  wordpress-plugin/sitewell-static-frontend/tests/Unit
```

The unit suite covers path resolution, nested pages, query strings, assets, missing files, traversal attempts, sensitive extensions, static-root containment, request methods, and WordPress bypass contexts. A real WordPress smoke test remains required because web-server rewrite behaviour cannot be proven by isolated unit tests.

## Manual verification

1. Install and activate the plugin on a disposable WordPress installation.
2. Confirm the original WordPress frontend remains visible.
3. Enable static mode under Settings → Sitewell Static Frontend.
4. Visit `/`, `/services/`, `/services/boiler-installation/`, and `/new-service/`.
5. Confirm the CSS, JavaScript, and SVG fixture assets load.
6. Confirm an unknown path displays the static 404 document and returns HTTP status 404.
7. Confirm `/wp-admin/`, `/wp-login.php`, and `/wp-json/` continue to work.
8. Clear the enable checkbox and save.
9. Confirm the original WordPress frontend returns immediately.
10. Deactivate the plugin and confirm WordPress remains unaffected.

## Hosting assumption

This compatibility mode assumes the web server sends requests for nonexistent clean URLs and assets to WordPress's normal front controller. That is the standard pretty-permalink arrangement on common Apache and Nginx WordPress installations. A host with custom rewrite rules that rejects unknown paths before WordPress receives them will need provider cooperation.

The proof of concept streams static assets through PHP. A later production mode should expose versioned release assets from the uploads directory so the web server can serve them directly.
