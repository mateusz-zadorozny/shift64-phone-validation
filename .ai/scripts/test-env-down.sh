#!/bin/sh
# om-prepare-test-env: generated teardown (contract v2)
# regenerate with: om-prepare-test-env --regenerate
# history:
#   2026-09-22 generated. Stops the wp-env containers this checkout started (DB volume
#              kept so the next boot is warm); --destroy removes containers and volumes.
#   2026-10-14 a root caller is handed to the same unprivileged user as test-env-up.sh:
#              wp-env keeps its state in that user's home. Keep this when regenerating.
set -eu

ROOT=$(cd "$(dirname "$0")/../.." && pwd)
cd "$ROOT"

# --- host guard: drive wp-env as the user test-env-up.sh started it as ---
if [ "$(id -u)" = 0 ]; then
	RUN_AS=${TEST_ENV_RUN_AS:-wpenv}
	if ! id "$RUN_AS" >/dev/null 2>&1; then
		echo "test-env-down: running as root, which wp-env cannot do. Create user '$RUN_AS' (README, Development > Tests), or set TEST_ENV_RUN_AS to an existing one." >&2
		exit 1
	fi
	exec sudo -u "$RUN_AS" -H env PATH="$PATH" sh "$ROOT/.ai/scripts/test-env-down.sh" "$@"
fi

QA_DIR=.ai/qa
ENV_DESCRIPTOR=$QA_DIR/test-env.json
LOCK_DIR=$QA_DIR/test-env.lock
DESTROY=0
[ "${1:-}" = "--destroy" ] && DESTROY=1

if [ ! -f "$ENV_DESCRIPTOR" ]; then
	echo "test-env-down: no descriptor at $ENV_DESCRIPTOR, nothing to stop"
	exit 0
fi

if grep -q '"startedByThisRepo": *true' "$ENV_DESCRIPTOR"; then
	if [ -x node_modules/.bin/wp-env ]; then
		if [ "$DESTROY" = 1 ]; then
			echo "test-env-down: wp-env destroy (containers + volumes of this checkout)"
			yes | npx --no-install wp-env destroy || true
		else
			echo "test-env-down: wp-env stop"
			npx --no-install wp-env stop || true
		fi
	else
		echo "test-env-down: node_modules/.bin/wp-env missing, cannot drive wp-env; containers left as they are" >&2
	fi
else
	echo "test-env-down: environment was not started by this repo, leaving it running"
fi

sed 's/"status": *"running"/"status": "stopped"/' "$ENV_DESCRIPTOR" > "$ENV_DESCRIPTOR.tmp" && mv "$ENV_DESCRIPTOR.tmp" "$ENV_DESCRIPTOR"
rm -rf "$LOCK_DIR"
echo "TEST_ENV_STATUS=stopped"
