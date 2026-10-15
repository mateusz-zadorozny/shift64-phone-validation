#!/bin/sh
# Seed the wp-env WordPress instance with the minimum WooCommerce setup
# needed to place an order at checkout. Idempotent: safe to run on every
# `wp-env start` (wired through lifecycleScripts.afterStart in .wp-env.json).
#
# The demo network repeats this store baseline in PHP, in
# tests/e2e/demo/mu-plugins/shift64-demo/seed-site.php: change both together.
#
# Usage: sh tests/e2e/bin/seed.sh [cli ...]
#   No arguments seeds the default `cli` instance.
set -eu

ROOT="$(cd "$(dirname "$0")/../../.." && pwd)"
cd "$ROOT"

if [ ! -f "$ROOT/vendor/autoload.php" ]; then
	echo "seed: vendor/autoload.php is missing - run 'composer install' first, the plugin cannot boot without it." >&2
	exit 1
fi

INSTANCES="${*:-cli}"

seed_instance() {
	instance="$1"
	wp() { npx --no-install wp-env run "$instance" wp "$@"; }

	echo "seed: [$instance] store settings"
	wp rewrite structure '/%postname%/' --hard >/dev/null
	wp option update woocommerce_coming_soon no >/dev/null
	wp option update woocommerce_onboarding_profile '{"skipped":true,"completed":true}' --format=json >/dev/null
	wp option update woocommerce_store_address 'ul. Testowa 1' >/dev/null
	wp option update woocommerce_store_city 'Warszawa' >/dev/null
	wp option update woocommerce_store_postcode '00-001' >/dev/null
	wp option update woocommerce_default_country 'PL' >/dev/null
	wp option update woocommerce_currency 'PLN' >/dev/null
	wp option update woocommerce_enable_guest_checkout yes >/dev/null
	wp option update woocommerce_enable_checkout_login_reminder no >/dev/null
	wp option update woocommerce_enable_signup_and_login_from_checkout no >/dev/null

	echo "seed: [$instance] payment (cash on delivery) and shipping (flat rate, rest of world)"
	wp wc payment_gateway update cod --enabled=true --user=admin >/dev/null
	if ! wp wc shipping_zone_method list 0 --user=admin --format=ids | grep -q .; then
		wp wc shipping_zone_method create 0 --method_id=flat_rate --settings='{"cost":"10"}' --user=admin >/dev/null
	fi

	echo "seed: [$instance] product"
	if [ -z "$(wp post list --post_type=product --name=e2e-test-product --field=ID)" ]; then
		wp wc product create --name='E2E Test Product' --slug=e2e-test-product --type=simple --regular_price=10 --user=admin >/dev/null
	fi

	echo "seed: [$instance] classic (shortcode) checkout page"
	if [ -z "$(wp post list --post_type=page --name=classic-checkout --field=ID)" ]; then
		wp post create --post_type=page --post_status=publish --post_title='Classic Checkout' --post_name=classic-checkout --post_content='[woocommerce_checkout]' >/dev/null
	fi

	echo "seed: [$instance] WooCommerce checkout page = the Checkout block page at /checkout/"
	# classic-checkout-block-theme-highlight.spec.ts relies on this layout: the
	# block page configured, the classic page alongside it (#34). Reset it in
	# case it was changed by hand. WooCommerce creates the 'checkout' page on
	# install.
	checkout_page_id="$(wp post list --post_type=page --post_status=publish --name=checkout --format=ids)"
	case "$checkout_page_id" in
		'' | *[!0-9]*)
			echo "seed: [$instance] expected exactly one published page with the slug 'checkout', got: '$checkout_page_id'" >&2
			exit 1
			;;
	esac
	wp option update woocommerce_checkout_page_id "$checkout_page_id" >/dev/null

	echo "seed: [$instance] Polish language packs (installed, not activated)"
	# block-checkout-polish-messages.spec.ts switches the site to pl_PL and back.
	# Installing an installed language only logs "already installed".
	wp language core install pl_PL >/dev/null
	# wp-env unpacks WooCommerce into woocommerce.latest-stable/, so WP-CLI cannot
	# read its version and requests the pack for the WordPress version number:
	# the WooCommerce wording may be older than WooCommerce itself. No spec relies
	# on it, so a failed download only warns.
	wp language plugin install woocommerce pl_PL >/dev/null ||
		echo "seed: [$instance] warning: WooCommerce pl_PL language pack not installed" >&2
	# Every other spec expects English: undo a run interrupted while in Polish.
	wp site switch-language en_US >/dev/null

	echo "seed: [$instance] classic theme (installed, not activated)"
	# classic-checkout-classic-theme-highlight.spec.ts switches to it and back:
	# only a classic theme renders the classic `ul.woocommerce-error` notices.
	if ! wp theme is-installed twentytwentyone; then
		wp theme install twentytwentyone >/dev/null
	fi
	# Every other spec expects the default block theme: undo a run interrupted
	# while the classic theme was active.
	if [ "$(wp option get stylesheet)" = twentytwentyone ]; then
		wp theme activate "$(wp eval 'echo WP_DEFAULT_THEME;')" >/dev/null
	fi

	echo "seed: [$instance] plugin defaults"
	wp option update shift64_phone_validation_enabled yes >/dev/null
	wp option update shift64_phone_validation_default_country PL >/dev/null
	wp option update shift64_phone_validation_validation_mode default_and_international >/dev/null
	wp option update shift64_phone_validation_output_format E164 >/dev/null

	echo "seed: [$instance] done"
}

for i in $INSTANCES; do
	seed_instance "$i"
done
