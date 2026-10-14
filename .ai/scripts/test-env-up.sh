#!/bin/sh
# om-prepare-test-env: generated entrypoint (contract v2)
# regenerate with: om-prepare-test-env --regenerate
# history:
#   2026-09-22 generated (cold 126s incl. composer install + npm ci, warm 1s reused,
#              --force restart 120s; cache spot check: touching composer.json re-ran the
#              preparation chain). Wraps the repo's own
#              recipe: .wp-env.json (WordPress + WooCommerce + this plugin, Docker via
#              @wordpress/env) whose afterStart hook runs tests/e2e/bin/seed.sh. Plugin
#              code is bind-mounted, so PHP edits are live without a restart; only the
#              env recipe/seed (REPROVISION_INPUTS) and the lockfiles invalidate reuse.
#              TTL defaults to 24h because a cold boot costs minutes and the deep
#              probes (Store API + admin login) already catch a dead database.
#   2026-10-14 host guards for agent hosts: a root caller is handed to an unprivileged
#              user (WP-CLI in wp-env's cli container refuses root), npm ci installs
#              devDependencies even under NODE_ENV=production, and an older default php
#              is replaced by php8.3+ when installed. Keep these when regenerating.
set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT"

# --- host guard: never run wp-env as root ---
# wp-env runs WP-CLI in its cli container as the calling user, WP-CLI refuses root, and
# wp-env cannot pass WP_CLI_ALLOW_ROOT through. Hand the run to TEST_ENV_RUN_AS (a user
# in the docker group with access to this checkout; README, Development > Tests).
if [ "$(id -u)" = 0 ]; then
	RUN_AS=${TEST_ENV_RUN_AS:-wpenv}
	if ! id "$RUN_AS" >/dev/null 2>&1; then
		echo "test-env-up: running as root, which wp-env cannot do (WP-CLI refuses root). Create user '$RUN_AS' in the docker group with access to this checkout (README, Development > Tests), or set TEST_ENV_RUN_AS to an existing one." >&2
		exit 1
	fi
	echo "test-env-up: running as root, handing over to user '$RUN_AS'"
	exec sudo -u "$RUN_AS" -H env PATH="$PATH" WP_ENV_PORT="${WP_ENV_PORT:-}" \
		TEST_ENV_CACHE_TTL_SECONDS="${TEST_ENV_CACHE_TTL_SECONDS:-}" \
		sh "$ROOT/.ai/scripts/test-env-up.sh" "$@"
fi

# --- host guard: composer.json needs PHP 8.3+ ---
# Put the newest installed php8.x first on PATH when the default php is older.
if ! php -r 'exit(PHP_VERSION_ID >= 80300 ? 0 : 1);' 2>/dev/null; then
	for v in 8.5 8.4 8.3; do
		if PHP_BIN=$(command -v "php$v" 2>/dev/null); then
			mkdir -p .ai/qa/bin
			ln -sf "$PHP_BIN" .ai/qa/bin/php
			PATH="$ROOT/.ai/qa/bin:$PATH"; export PATH
			echo "test-env-up: default php is older than 8.3, using $PHP_BIN"
			break
		fi
	done
fi

# --- project-specific variables (tweak here, not in the logic below) ---
QA_DIR=.ai/qa
ENV_DESCRIPTOR=$QA_DIR/test-env.json
BUILD_CACHE=$QA_DIR/test-env-build-cache.json
CREDENTIALS_FILE=$QA_DIR/test-env.env
LOCK_DIR=$QA_DIR/test-env.lock
PREFERRED_PORT=${WP_ENV_PORT:-8888}          # wp-env honors WP_ENV_PORT; .wp-env.json says 8888
HEALTH_PATH=/
API_PROBE_PATH=/wp-json/wc/store/v1/products # unauthenticated Store API, seeded product must be listed
API_PROBE_EXPECT=e2e-test-product
ADMIN_USER=admin                              # wp-env's built-in demo account (disposable)
ADMIN_PASSWORD_DEMO=password                  # written to CREDENTIALS_FILE, never printed
UP_COMMAND="npx --no-install wp-env start"    # seeds through lifecycleScripts.afterStart
BUILD_INPUTS="composer.json composer.lock package.json package-lock.json"
BUILD_ENV_VARS=""
ARTIFACTS="vendor/autoload.php node_modules/.bin/wp-env"
REPROVISION_INPUTS=".wp-env.json tests/e2e/bin/seed.sh"
TTL=${TEST_ENV_CACHE_TTL_SECONDS:-86400}
BOOT_TIMEOUT=900
BROWSER_PROVIDER=agent-browser
BROWSER_DESCRIPTOR=.ai/browsers/agent-browser.md
TEST_RUNNER_NAME=playwright
TEST_RUNNER_CONFIG=playwright.config.ts

FORCE=0; FORCE_REBUILD=0
for arg in "$@"; do
	case "$arg" in
		--force) FORCE=1 ;;
		--force-rebuild) FORCE_REBUILD=1 ;;
		*) echo "test-env-up: unknown flag $arg" >&2; exit 2 ;;
	esac
done

mkdir -p "$QA_DIR"
PLATFORM=darwin
case "$(uname -s 2>/dev/null || echo unknown)" in
	Linux*) grep -qiE 'microsoft|wsl' /proc/version 2>/dev/null && PLATFORM=wsl2 || PLATFORM=linux ;;
	Darwin*) PLATFORM=darwin ;;
	MINGW*|MSYS*|CYGWIN*) PLATFORM=win32 ;;
esac

# --- lock: one bootstrap at a time ---
LOCKED=0
release_lock() { [ "$LOCKED" = 1 ] && rm -rf "$LOCK_DIR"; }
acquire_lock() {
	waited=0
	while ! mkdir "$LOCK_DIR" 2>/dev/null; do
		owner_pid=$(sed -n 's/.*"pid": *\([0-9]*\).*/\1/p' "$LOCK_DIR/owner.json" 2>/dev/null || true)
		if [ -z "$owner_pid" ] || ! kill -0 "$owner_pid" 2>/dev/null; then
			# no owner recorded (crashed mid-acquire) or owner dead -> stale
			[ "$waited" -ge 5 ] || [ -n "$owner_pid" ] && { rm -rf "$LOCK_DIR"; continue; }
		fi
		if [ "$waited" -ge 300 ]; then
			echo "test-env-up: lock $LOCK_DIR held by pid ${owner_pid:-?} for 5 min, giving up" >&2
			exit 1
		fi
		sleep 5; waited=$((waited + 5))
	done
	LOCKED=1
	printf '{ "pid": %s, "source": "test-env-up.sh", "acquiredAt": "%s" }\n' "$$" "$(date -u +%FT%TZ)" > "$LOCK_DIR/owner.json"
	trap release_lock EXIT
	trap 'exit 130' INT
	trap 'exit 143' TERM
}
acquire_lock

# --- helpers ---
descriptor_str() { sed -n "s/.*\"$1\": *\"\([^\"]*\)\".*/\1/p" "$ENV_DESCRIPTOR" 2>/dev/null | head -1; }
descriptor_num() { sed -n "s/.*\"$1\": *\([0-9]*\).*/\1/p" "$ENV_DESCRIPTOR" 2>/dev/null | head -1; }

free_port() {
	if command -v python3 >/dev/null 2>&1; then
		python3 -c 'import socket;s=socket.socket();s.bind(("127.0.0.1",0));print(s.getsockname()[1]);s.close()'
	elif command -v node >/dev/null 2>&1; then
		node -e 's=require("net").createServer();s.listen(0,"127.0.0.1",()=>{console.log(s.address().port);s.close()})'
	else
		awk 'BEGIN{srand();print 20000+int(rand()*20000)}'
	fi
}

# Our wp-env containers are the ones publishing :<port>->80 under a wp-env-* compose project.
containers_alive() {
	docker ps --format '{{.Names}}|{{.Ports}}' 2>/dev/null | grep '^wp-env-' | grep -q ":$1->80/tcp"
}
port_in_use() { curl -s -m 3 -o /dev/null "http://127.0.0.1:$1/" 2>/dev/null; }

write_credentials() {
	printf 'TEST_ADMIN_PASSWORD=%s\n' "$ADMIN_PASSWORD_DEMO" > "$CREDENTIALS_FILE"
	grep -qxF "$CREDENTIALS_FILE" .gitignore 2>/dev/null || printf '%s\n' "$CREDENTIALS_FILE" >> .gitignore
}

# Readiness at increasing depth: shell -> Store API (needs DB + seed) -> admin login round trip.
probe() {
	url=$1
	curl -fsS -m 10 -o /dev/null "$url$HEALTH_PATH" || return 1
	curl -fsS -m 20 "$url$API_PROBE_PATH" 2>/dev/null | grep -q "$API_PROBE_EXPECT" || return 1
	[ -f "$CREDENTIALS_FILE" ] || write_credentials
	set -a; . "./$CREDENTIALS_FILE"; set +a
	jar=$(mktemp)
	curl -sS -m 20 -o /dev/null -c "$jar" -b 'wordpress_test_cookie=WP%20Cookie%20check' \
		--data-urlencode "log=$ADMIN_USER" --data-urlencode "pwd=$TEST_ADMIN_PASSWORD" \
		--data 'wp-submit=Log+In' --data 'testcookie=1' "$url/wp-login.php" || { rm -f "$jar"; return 1; }
	if grep -q 'wordpress_logged_in' "$jar"; then rm -f "$jar"; return 0; fi
	rm -f "$jar"; return 1
}

# --- reuse check: attach, don't reboot ---
REUSED=0
if [ "$FORCE" != 1 ] && [ -f "$ENV_DESCRIPTOR" ] && [ "$(descriptor_str status)" = "running" ]; then
	OLD_URL=$(descriptor_str baseUrl)
	OLD_PORT=$(descriptor_num port)
	OLD_EPOCH=$(descriptor_num startedEpoch)
	AGE=$(( $(date +%s) - ${OLD_EPOCH:-0} ))
	STALE=""
	containers_alive "${OLD_PORT:-0}" || STALE="containers gone"
	[ -n "$STALE" ] || probe "$OLD_URL" || STALE="readiness probe failed"
	[ -n "$STALE" ] || [ "$AGE" -le "$TTL" ] || STALE="older than TTL (${AGE}s)"
	if [ -z "$STALE" ]; then
		for f in $REPROVISION_INPUTS; do
			[ -f "$f" ] && [ "$f" -nt "$ENV_DESCRIPTOR" ] && STALE="$f changed since boot"
		done
	fi
	if [ -z "$STALE" ]; then
		REUSED=1
		echo "test-env-up: reusing healthy environment at $OLD_URL"
	else
		echo "test-env-up: recorded environment is stale ($STALE), restarting"
		if grep -q '"startedByThisRepo": *true' "$ENV_DESCRIPTOR" && [ -x node_modules/.bin/wp-env ]; then
			npx --no-install wp-env stop >/dev/null 2>&1 || true
		fi
	fi
elif [ "$FORCE" = 1 ] && [ -f "$ENV_DESCRIPTOR" ] && grep -q '"startedByThisRepo": *true' "$ENV_DESCRIPTOR" && [ -x node_modules/.bin/wp-env ]; then
	echo "test-env-up: --force, stopping the recorded environment first"
	npx --no-install wp-env stop >/dev/null 2>&1 || true
fi

if [ "$REUSED" = 1 ]; then
	BASE_URL=$OLD_URL
else
	START_TS=$(date +%s)

	# --- build cache (generic; only the three lists are project-specific) ---
	fp_file() { stat -f '%z:%m' "$1" 2>/dev/null || stat -c '%s:%Y' "$1" 2>/dev/null; }
	fingerprint() {
		{
			for p in $BUILD_INPUTS; do
				if [ -d "$p" ]; then
					find "$p" -type f ! -path '*/node_modules/*' ! -path '*/.git/*' ! -path '*/vendor/*'
				elif [ -f "$p" ]; then echo "$p"; fi
			done | LC_ALL=C sort | while IFS= read -r f; do printf '%s:%s\n' "$f" "$(fp_file "$f")"; done
			for v in $BUILD_ENV_VARS; do eval "printf 'env:%s=%s\n' \"$v\" \"\${$v:-}\""; done
		} | cksum | awk '{print $1"-"$2}'
	}
	build_needed() {
		[ "$FORCE_REBUILD" = 1 ] && return 0
		[ -f "$BUILD_CACHE" ] || return 0
		CACHED_FP=$(sed -n 's/.*"sourceFingerprint": *"\([^"]*\)".*/\1/p' "$BUILD_CACHE")
		CACHED_ROOT=$(sed -n 's/.*"projectRoot": *"\([^"]*\)".*/\1/p' "$BUILD_CACHE")
		[ "$CACHED_FP" = "$(fingerprint)" ] || return 0
		[ "$CACHED_ROOT" = "$(pwd)" ] || return 0
		for a in $ARTIFACTS; do [ -s "$a" ] || [ -d "$a" ] || return 0; done
		return 1
	}
	if build_needed; then
		echo "test-env-up: preparing workspace (composer install, npm ci)"
		composer install --prefer-dist --no-interaction --quiet
		# --include=dev: wp-env and Playwright are devDependencies, which a host-wide
		# NODE_ENV=production would otherwise skip.
		npm ci --include=dev --no-audit --no-fund --loglevel=error
		printf '{ "builtAt": "%s", "sourceFingerprint": "%s", "projectRoot": "%s", "artifactPaths": "%s" }\n' \
			"$(date -u +%FT%TZ)" "$(fingerprint)" "$(pwd)" "$ARTIFACTS" > "$BUILD_CACHE"
	else
		echo "test-env-up: workspace up to date (build cache hit)"
	fi

	# --- port: stable preferred port unless a foreign process holds it ---
	PORT=$PREFERRED_PORT
	if port_in_use "$PORT" && ! containers_alive "$PORT"; then
		PORT=$(free_port)
		echo "test-env-up: port $PREFERRED_PORT is taken by something else, using $PORT"
	fi
	export WP_ENV_PORT=$PORT
	BASE_URL="http://localhost:$PORT"

	# --- services + app: the repo's own up-command (Docker: wordpress, mysql, cli) ---
	write_credentials
	echo "test-env-up: $UP_COMMAND (WP_ENV_PORT=$PORT)"
	$UP_COMMAND

	# --- health wait (bounded) ---
	waited=0
	until probe "$BASE_URL"; do
		if [ "$waited" -ge "$BOOT_TIMEOUT" ]; then
			echo "test-env-up: $BASE_URL not healthy after ${BOOT_TIMEOUT}s" >&2
			exit 1
		fi
		sleep 5; waited=$((waited + 5))
	done
	BOOT_SECONDS=$(( $(date +%s) - START_TS ))

	# --- browser provider state (verified at generation; never reinstalled here) ---
	if BROWSER_BIN=$(command -v "$BROWSER_PROVIDER" 2>/dev/null); then
		BROWSER_INSTALLED=true
		BROWSER_VERSION=$("$BROWSER_BIN" --version 2>/dev/null | head -1 || echo unknown)
		BROWSER_NOTES=""
	else
		BROWSER_INSTALLED=false; BROWSER_BIN=""; BROWSER_VERSION=unknown
		BROWSER_NOTES="$BROWSER_PROVIDER not on PATH; run om-prepare-test-env to repair through $BROWSER_DESCRIPTOR"
	fi

	# --- descriptor ---
	RUN_ID="$(date -u +%Y%m%d%H%M%S)-$$"
	NOW=$(date -u +%FT%TZ)
	# Scope service lookups to the compose project that publishes our port (other worktrees run their own wp-env).
	PROJECT=$(docker ps --format '{{.Label "com.docker.compose.project"}}|{{.Ports}}' | grep ":$PORT->80/tcp" | cut -d'|' -f1 | head -1)
	MYSQL_PORT=$(docker ps --filter "label=com.docker.compose.project=$PROJECT" --format '{{.Names}}|{{.Ports}}' | grep -- '-mysql-1|' | sed -n 's/.*:\([0-9]*\)->3306.*/\1/p' | head -1)
	cat > "$ENV_DESCRIPTOR" <<EOF
{
  "version": 1,
  "runId": "$RUN_ID",
  "status": "running",
  "mode": "docker",
  "baseUrl": "$BASE_URL",
  "startedByThisRepo": true,
  "startScript": ".ai/scripts/test-env-up.sh",
  "stopScript": ".ai/scripts/test-env-down.sh",
  "app": { "startCommand": "$UP_COMMAND", "port": $PORT, "healthPath": "$HEALTH_PATH", "pid": 0 },
  "services": [
    { "type": "mysql", "host": "127.0.0.1", "port": ${MYSQL_PORT:-0}, "container": "$PROJECT-mysql-1", "url": "", "env": {} },
    { "type": "wordpress", "host": "127.0.0.1", "port": $PORT, "container": "$PROJECT-wordpress-1", "url": "$BASE_URL", "env": {} }
  ],
  "credentials": [ { "role": "admin", "username": "$ADMIN_USER", "passwordEnv": "TEST_ADMIN_PASSWORD" } ],
  "credentialsFile": "$CREDENTIALS_FILE",
  "browser": {
    "provider": "$BROWSER_PROVIDER",
    "installed": $BROWSER_INSTALLED,
    "command": "$BROWSER_BIN",
    "version": "$BROWSER_VERSION",
    "descriptor": "$BROWSER_DESCRIPTOR",
    "notes": "$BROWSER_NOTES"
  },
  "testRunner": { "name": "$TEST_RUNNER_NAME", "config": "$TEST_RUNNER_CONFIG" },
  "platform": "$PLATFORM",
  "startedAt": "$NOW",
  "startedEpoch": $(date +%s),
  "notes": "Boot ${BOOT_SECONDS}s. WordPress latest + WooCommerce latest + this plugin (bind-mounted: PHP edits are live). Seeded by tests/e2e/bin/seed.sh on every start: PL store, guest checkout, cash on delivery, flat rate, product /product/e2e-test-product/, block checkout /checkout/, classic /classic-checkout/. Admin at /wp-admin/ (see credentials). wp-env publishes ports on 0.0.0.0, not 127.0.0.1. Committed e2e suite: npx playwright test (reads WP_ENV_PORT / WP_BASE_URL). Stop: sh .ai/scripts/test-env-down.sh (keeps the DB volume; --destroy wipes it)."
}
EOF
fi

printf 'TEST_ENV_STATUS=running\nTEST_ENV_BASE_URL=%s\nTEST_ENV_DESCRIPTOR=%s\nTEST_ENV_REUSED=%s\nBROWSER_PROVIDER=%s\nBROWSER_INSTALLED=%s\n' \
	"$BASE_URL" "$ENV_DESCRIPTOR" "$REUSED" "$BROWSER_PROVIDER" "$( [ "$(descriptor_str provider)" = "$BROWSER_PROVIDER" ] && grep -q '"installed": *true' "$ENV_DESCRIPTOR" && echo 1 || echo 0 )"
