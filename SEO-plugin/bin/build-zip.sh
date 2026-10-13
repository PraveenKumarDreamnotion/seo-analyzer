#!/bin/sh
# Builds an installable plugin ZIP from a commit. Needs git, composer and zip.
#
#   sh bin/build-zip.sh [output-dir] [git-ref]
#
# On a machine without PHP, run it in the Composer container from the repository root:
#   docker run --rm -v "$PWD:/repo" -w /repo/SEO-plugin composer:2 sh bin/build-zip.sh /repo/dist
#
# The ZIP holds one folder, seo-analyzer/, with the production Composer packages inside.
# Tests, the test harness and this script are left out (see .gitattributes, export-ignore).
set -eu

here=$(cd "$(dirname "$0")/.." && pwd)
out=${1:-"$here/../dist"}
ref=${2:-HEAD}
git="git -c safe.directory=* -C $here"

version=$($git show "$ref:SEO-plugin/seo-analyzer.php" | sed -n "s/^define('SEO_ANALYZER_VERSION', '\(.*\)');.*/\1/p")
[ -n "$version" ] || { echo "Could not read the version from seo-analyzer.php" >&2; exit 1; }

work=$(mktemp -d)
trap 'rm -rf "$work"' EXIT

$git archive --format=tar --prefix=seo-analyzer/ "$ref:SEO-plugin" | tar -x -C "$work"

# ext-gd is needed on the web server, not on the build machine.
composer install --working-dir="$work/seo-analyzer" --no-dev --optimize-autoloader --no-interaction --no-progress --ignore-platform-req=ext-gd

# Not needed at run time.
find "$work/seo-analyzer/vendor" -type d \( -name tests -o -name Tests -o -name test -o -name docs -o -name .github \) -prune -exec rm -rf {} +
find "$work/seo-analyzer" \( -name .DS_Store -o -name .gitignore -o -name .gitattributes -o -name '*.md' -path '*/vendor/*' \) -type f -delete

mkdir -p "$out"
zip_path="$(cd "$out" && pwd)/halo-audit-$version.zip"
rm -f "$zip_path"
(cd "$work" && zip -q -r -X "$zip_path" seo-analyzer)

echo "Built $zip_path"
echo "Commit: $($git rev-parse --short "$ref")"
echo "Files: $(unzip -Z1 "$zip_path" | grep -vc '/$')"
echo "Size: $(du -h "$zip_path" | cut -f1)"
