# Sitewell by Digizu

Sitewell by Digizu connects a WordPress installation to the Sitewell website-care service. It downloads authenticated, verified website releases while preserving access to WordPress administration and operational routes.

## Development

Install dependencies and run the checks from this directory:

```bash
composer install
composer test
composer lint
```

Build the installable archive with:

```bash
composer package
```

The resulting archive is written to `build/sitewell-by-digizu.zip`.

## Configuration

Production uses `https://sitewell.digizu.co.uk/api`. Development installations may define `SITEWELL_STATIC_FRONTEND_API_URL` in `wp-config.php` before activating the plugin.

Release files default to the WordPress uploads directory. Hosts may define `SITEWELL_STATIC_FRONTEND_RELEASES_PATH` to use a dedicated location whose final path components are `sitewell-static-frontend/releases`.

## Licence

GPLv2 or later. See `license.txt`.
