#!/usr/bin/env sh
set -eu

plugin_root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
build_root="$plugin_root/build"
package_root="$build_root/sitewell-static-frontend"
archive="$build_root/sitewell-static-frontend.zip"

rm -rf "$package_root" "$archive"
mkdir -p "$package_root"

cp "$plugin_root/sitewell-static-frontend.php" "$package_root/"
cp -R "$plugin_root/src" "$package_root/src"
cp -R "$plugin_root/templates" "$package_root/templates"
cp -R "$plugin_root/fixture-site" "$package_root/fixture-site"
cp "$plugin_root/README.md" "$package_root/"

cd "$build_root"
zip -qr "$archive" sitewell-static-frontend

printf '%s\n' "$archive"
