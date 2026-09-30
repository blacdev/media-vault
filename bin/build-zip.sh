#!/usr/bin/env bash
# Build dist/secure-media-vault.zip: an installable plugin zip without development files.
set -euo pipefail

cd "$(dirname "$0")/.."
SLUG="secure-media-vault"
VERSION="$(grep -m1 "Version:" "$SLUG.php" | awk '{print $NF}')"

rm -rf "dist/$SLUG" "dist/$SLUG.zip"
mkdir -p "dist/$SLUG"
rsync -a --exclude-from=.distignore ./ "dist/$SLUG/"
(cd dist && zip -rq "$SLUG.zip" "$SLUG")
rm -rf "dist/$SLUG"

echo "Built dist/$SLUG.zip (version $VERSION)"
