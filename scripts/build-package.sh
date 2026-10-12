#!/bin/bash
# Builds the distributable plugin folder and ZIP from the current working tree.
#
# The SAME folder is committed to WordPress.org SVN trunk (BUILD_DIR of the 10up deploy
# action) and zipped for the GitHub release, so both always carry identical files.
# What is left out is defined only in .distignore.
#
# Needs production dependencies first:
#   composer install --no-dev --optimize-autoloader --prefer-dist
#
# Usage: ./scripts/build-package.sh <output-dir>
#   <output-dir>/shift64-phone-validation/       plugin folder
#   <output-dir>/shift64-phone-validation.zip    installable ZIP
# In GitHub Actions the two paths are also written to $GITHUB_OUTPUT as "dir" and "zip".

set -euo pipefail

SLUG="shift64-phone-validation"
MAX_ZIP_BYTES=$((10 * 1024 * 1024)) # WordPress.org submission limit: "under 10Mb".

OUT="${1:-}"
if [ -z "$OUT" ]; then
	echo "Usage: $0 <output-dir>" >&2
	exit 1
fi

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
mkdir -p "$OUT"
OUT="$(cd "$OUT" && pwd)"

# An output folder inside the plugin would be copied into itself, unless .distignore drops it.
case "$OUT/" in
	"$ROOT/dist/"*) ;;
	"$ROOT/"*)
		echo "Output dir must be outside the plugin directory (or under dist/)." >&2
		exit 1
		;;
esac

# The package must carry the production autoloader, never dev tools (PHPUnit, PHPCS, ...).
if [ ! -f "$ROOT/vendor/autoload.php" ]; then
	echo "vendor/autoload.php missing: run composer install --no-dev --optimize-autoloader" >&2
	exit 1
fi
# Composer 2 records `"dev": false` in installed.json only for a --no-dev install.
# shellcheck disable=SC2016 # $argv and $j are PHP, not shell.
if ! php -r '$j = json_decode( (string) @file_get_contents( $argv[1] ), true ); exit( is_array( $j ) && false === ( $j["dev"] ?? null ) ? 0 : 1 );' "$ROOT/vendor/composer/installed.json"; then
	echo "vendor/ is not a production install: run composer install --no-dev --optimize-autoloader" >&2
	exit 1
fi

PLUGIN_DIR="$OUT/$SLUG"
ZIP="$OUT/$SLUG.zip"
rm -rf "$PLUGIN_DIR" "$ZIP"
mkdir -p "$PLUGIN_DIR"

rsync -a --exclude-from="$ROOT/.distignore" "$ROOT/" "$PLUGIN_DIR/"

# Guard rails: fail instead of shipping something Plugin Check or the review team rejects.
fail=0
for required in "$SLUG.php" readme.txt LICENSE composer.json vendor/autoload.php; do
	if [ ! -e "$PLUGIN_DIR/$required" ]; then
		echo "::error::Package is missing $required" >&2
		fail=1
	fi
done

# Plugin Check error stable_tag_mismatch: readme "Stable tag" must equal the header "Version".
stable_tag="$(sed -n 's/^Stable tag:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$PLUGIN_DIR/readme.txt" | head -n 1)"
header_version="$(sed -n 's/^[[:space:]]*\*[[:space:]]*Version:[[:space:]]*\([^[:space:]]*\).*/\1/p' "$PLUGIN_DIR/$SLUG.php" | head -n 1)"
if [ -z "$stable_tag" ] || [ "$stable_tag" != "$header_version" ]; then
	echo "::error::readme.txt Stable tag '$stable_tag' does not match the plugin header Version '$header_version'" >&2
	fail=1
fi

# Plugin Check File_Type_Check: hidden files, application files, archives, symlinks.
rejected="$(cd "$PLUGIN_DIR" && find . -mindepth 1 \( -name '.*' -o -type l \
	-o -name '*.a' -o -name '*.bin' -o -name '*.bpk' -o -name '*.deploy' -o -name '*.dist' \
	-o -name '*.distz' -o -name '*.dmg' -o -name '*.dms' -o -name '*.dump' -o -name '*.elc' \
	-o -name '*.exe' -o -name '*.iso' -o -name '*.lha' -o -name '*.lrf' -o -name '*.lzh' \
	-o -name '*.o' -o -name '*.obj' -o -name '*.phar' -o -name '*.pkg' -o -name '*.sh' -o -name '*.so' \
	-o -name '*.zip' -o -name '*.gz' -o -name '*.tgz' -o -name '*.rar' -o -name '*.tar' -o -name '*.7z' \) -print)"
# Plugin Check badly_named_files: whitespace or special characters in a file name.
badly_named="$(cd "$PLUGIN_DIR" && find . -mindepth 1 -name '*[][[:space:]!@#$%^&*()+={};:"'\''<>,?\\|`~]*' -print)"
# Markdown in the root other than the files Plugin Check allows (unexpected_markdown_file).
stray_md="$(cd "$PLUGIN_DIR" && find . -maxdepth 1 -type f -iname '*.md' ! -iname 'README.md' ! -iname 'CHANGELOG.md' \
	! -iname 'LICENSE.md' ! -iname 'CONTRIBUTING.md' ! -iname 'SECURITY.md' -print)"
if [ -n "$rejected$badly_named$stray_md" ]; then
	echo "::error::Package contains files that must not ship (add them to .distignore):" >&2
	printf '%s\n' "$rejected" "$badly_named" "$stray_md" | sed '/^$/d' >&2
	fail=1
fi

if [ "$fail" -ne 0 ]; then
	exit 1
fi

(cd "$OUT" && zip -qrX "$SLUG.zip" "$SLUG")

bytes=$(wc -c < "$ZIP" | tr -d ' ')
if [ "$bytes" -ge "$MAX_ZIP_BYTES" ]; then
	echo "::error::$SLUG.zip is $bytes bytes, the WordPress.org limit is 10 MB" >&2
	exit 1
fi

echo "Built $PLUGIN_DIR"
echo "Built $ZIP ($bytes bytes, $(find "$PLUGIN_DIR" -type f | wc -l | tr -d ' ') files)"

# Expose paths to GitHub Actions when running there.
if [ -n "${GITHUB_OUTPUT:-}" ]; then
	{
		echo "dir=$PLUGIN_DIR"
		echo "zip=$ZIP"
	} >> "$GITHUB_OUTPUT"
fi
