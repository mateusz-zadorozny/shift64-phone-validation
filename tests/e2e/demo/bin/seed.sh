#!/bin/sh
# Seed the demo network (.wp-env.demo.json): network-activate WooCommerce and
# the plugin, create one subsite per scenario in
# tests/e2e/demo/mu-plugins/shift64-demo/scenarios.php and seed each of them
# (seed-site.php). Idempotent: runs on every `npm run demo:start` through
# lifecycleScripts.afterStart, and resets every site to its scenario.
#
# Usage: sh tests/e2e/demo/bin/seed.sh
set -eu

ROOT="$(cd "$(dirname "$0")/../../../.." && pwd)"
cd "$ROOT"

CONFIG=.wp-env.demo.json
MU_DIR=/var/www/html/wp-content/mu-plugins/shift64-demo

if [ ! -f "$ROOT/vendor/autoload.php" ]; then
	echo "demo seed: vendor/autoload.php is missing - run 'composer install' first, the plugin cannot boot without it." >&2
	exit 1
fi

wp() { npx --no-install wp-env run --config="$CONFIG" cli wp "$@"; }
# wp-env wraps the command output in its own status lines: keep the lines
# that match the expected shape.
wp_lines() { pattern="$1"; shift; wp "$@" | tr -d '\r' | grep -E "$pattern" || true; }

echo "demo seed: language packs"
# The pl scenario would lie without the core pack: a failure stops the seed.
wp language core install pl_PL >/dev/null

echo "demo seed: classic theme, enabled for the network"
if ! wp theme is-installed twentytwentyone; then
	wp theme install twentytwentyone >/dev/null
fi
# On multisite a theme must be network-enabled before a subsite can use it.
wp theme enable twentytwentyone --network >/dev/null

echo "demo seed: network-activate WooCommerce and the plugin"
# wp-env activates the listed plugins on the main site only. It names this
# plugin after the checkout folder, which is not always shift64-phone-validation.
PLUGIN_DIR="$(basename "$ROOT")"
wp plugin activate woocommerce.latest-stable "$PLUGIN_DIR" --network >/dev/null

echo "demo seed: WooCommerce pl_PL language pack"
# Needs WooCommerce active to read its version (install-wc-language.php says why
# `wp language plugin install` is not used). Without it the /pl/ block checkout
# is half English: a failure stops the seed.
wp eval-file "$MU_DIR/install-wc-language.php" pl_PL

DOMAIN="$(wp_lines '^[A-Za-z0-9.-]+(:[0-9]+)?$' eval 'echo get_network()->domain, "\n";' | tail -n 1)"
if [ -z "$DOMAIN" ]; then
	echo "demo seed: could not read the network domain - is the demo multisite installed?" >&2
	exit 1
fi
SLUGS="$(wp_lines '^[a-z0-9-]+$' eval "foreach ( require '$MU_DIR/scenarios.php' as \$s ) { echo \$s['slug'], \"\\n\"; }")"
if [ -z "$SLUGS" ]; then
	echo "demo seed: no scenarios found in $MU_DIR/scenarios.php" >&2
	exit 1
fi
EXISTING="$(wp_lines '^/' site list --field=path)"

for slug in $SLUGS; do
	if ! printf '%s\n' "$EXISTING" | grep -qx "/$slug/"; then
		echo "demo seed: create site /$slug/"
		wp site create --slug="$slug" --title="$slug" >/dev/null
	fi
done

echo "demo seed: [index] network root"
wp --url="http://$DOMAIN/" eval-file "$MU_DIR/seed-site.php" index
for slug in $SLUGS; do
	wp --url="http://$DOMAIN/$slug/" eval-file "$MU_DIR/seed-site.php" "$slug"
done

echo "demo seed: done - http://$DOMAIN/"
