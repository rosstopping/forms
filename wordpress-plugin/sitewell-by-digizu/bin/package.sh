#!/usr/bin/env sh
set -eu

plugin_root=$(CDPATH='' cd -- "$(dirname -- "$0")/.." && pwd)
build_root="$plugin_root/build"
package_root="$build_root/sitewell-by-digizu"
archive="$build_root/sitewell-by-digizu.zip"

rm -rf "$package_root" "$archive"
mkdir -p "$package_root"

cp "$plugin_root/sitewell-static-frontend.php" "$package_root/"
cp -R "$plugin_root/src" "$package_root/src"
cp -R "$plugin_root/templates" "$package_root/templates"
cp "$plugin_root/readme.txt" "$package_root/"
cp "$plugin_root/license.txt" "$package_root/"
cp "$plugin_root/uninstall.php" "$package_root/"

cd "$build_root"
zip -qr "$archive" sitewell-by-digizu

printf '%s\n' "$archive"
