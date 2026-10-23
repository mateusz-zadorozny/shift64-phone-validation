#!/bin/sh
# Demo network: a subdirectory multisite on its own port (default 8890) with
# WooCommerce and this plugin network-activated and one subsite per scenario
# (tests/e2e/demo/mu-plugins/shift64-demo/scenarios.php). It runs next to the
# default single-site env (.wp-env.json, port 8888) and never touches it.
#
# Usage: sh tests/e2e/demo/bin/demo.sh start|stop|destroy [wp-env flags]
#   (npm run demo:start / demo:stop / demo:destroy; e.g. `npm run demo:start -- --update`,
#   `npm run demo:destroy -- --force`)
# Port: WP_DEMO_PORT (default 8890). WP_ENV_PORT is deliberately not read: the
# default env, playwright.config.ts and .ai/scripts/test-env-up.sh use it.
set -eu

ROOT=$(cd "$(dirname "$0")/../../../.." && pwd)
cd "$ROOT"

CONFIG=.wp-env.demo.json
PORT=${WP_DEMO_PORT:-8890}

case "${1:-}" in
	start | stop | destroy) COMMAND=$1 ;;
	*)
		echo "usage: sh tests/e2e/demo/bin/demo.sh start|stop|destroy" >&2
		exit 2
		;;
esac

# --- host guard: never run wp-env as root (same as .ai/scripts/test-env-up.sh) ---
# wp-env runs WP-CLI in its cli container as the calling user, WP-CLI refuses root, and
# wp-env cannot pass WP_CLI_ALLOW_ROOT through. Hand the run to TEST_ENV_RUN_AS (a user
# in the docker group with access to this checkout; README, Development > Tests).
if [ "$(id -u)" = 0 ]; then
	RUN_AS=${TEST_ENV_RUN_AS:-wpenv}
	if ! id "$RUN_AS" >/dev/null 2>&1; then
		echo "demo: running as root, which wp-env cannot do (WP-CLI refuses root). Create user '$RUN_AS' in the docker group with access to this checkout (README, Development > Tests), or set TEST_ENV_RUN_AS to an existing one." >&2
		exit 1
	fi
	echo "demo: running as root, handing over to user '$RUN_AS'"
	exec sudo -u "$RUN_AS" -H env PATH="$PATH" WP_DEMO_PORT="$PORT" \
		sh "$ROOT/tests/e2e/demo/bin/demo.sh" "$@"
fi

# --- host guard: composer.json needs PHP 8.3+ (same as .ai/scripts/test-env-up.sh) ---
# Put the newest installed php8.x first on PATH when the default php is older.
if [ "$COMMAND" = start ] && ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
	for v in 8.5 8.4 8.3; do
		if PHP_BIN=$(command -v "php$v" 2>/dev/null); then
			mkdir -p .ai/qa/bin
			ln -sf "$PHP_BIN" .ai/qa/bin/php
			PATH="$ROOT/.ai/qa/bin:$PATH"; export PATH
			echo "demo: default php is older than 8.3, using $PHP_BIN"
			break
		fi
	done
fi

if [ ! -x node_modules/.bin/wp-env ]; then
	echo "demo: node_modules/.bin/wp-env is missing - run 'npm ci --include=dev' first." >&2
	exit 1
fi

# WP_ENV_PORT is set for wp-env only, never exported from the caller's shell.
wp_env() { WP_ENV_PORT=$PORT npx --no-install wp-env "$@"; }

case "$COMMAND" in
	start)
		shift
		composer install --prefer-dist --no-interaction
		# The afterStart hook (tests/e2e/demo/bin/seed.sh) builds the network.
		if ! wp_env start --config="$CONFIG" "$@"; then
			echo "demo: wp-env could not start. If port $PORT is taken, pick another one: WP_DEMO_PORT=8891 npm run demo:start" >&2
			exit 1
		fi
		# The network stores its domain at install time: a port change needs a fresh network.
		domain=$(wp_env run --config="$CONFIG" cli wp eval 'echo get_network()->domain, "\n";' 2>/dev/null |
			tr -d '\r' | grep -E '^[A-Za-z0-9.-]+(:[0-9]+)?$' | tail -n 1 || true)
		if [ -n "$domain" ] && [ "$domain" != "localhost:$PORT" ]; then
			echo "demo: warning: the network was installed for '$domain', not 'localhost:$PORT'. Run 'npm run demo:destroy' and start again to change the port." >&2
		fi
		echo "demo: network ready at http://localhost:$PORT/ (admin / password)"
		;;
	stop | destroy)
		shift
		wp_env "$COMMAND" --config="$CONFIG" "$@"
		;;
esac
