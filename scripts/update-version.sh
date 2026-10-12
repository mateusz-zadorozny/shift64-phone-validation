#!/bin/bash
# Writes a release version into every place that carries it and prepends the new
# release to the readme.txt changelog.
#
# Called by @semantic-release/exec (prepareCmd) AFTER @semantic-release/changelog has
# added the new release to CHANGELOG.md and BEFORE @semantic-release/git commits.
# Every file changed here must be listed in .releaserc.json -> @semantic-release/git
# "assets", otherwise the change is made in the workspace but never reaches the git tag.
# release.yml catches that in "Verify workspace is the release commit", but that step runs
# AFTER the tag and the GitHub release were published: the job stops and the release has no
# ZIP. Recovery: fix the assets list, then run wporg-deploy.yml for that tag with upload-zip
# to attach the ZIP. If the missed file is readme.txt or the plugin file, the tag carries the
# old version and wporg-deploy.yml refuses it; the next release (a fix: commit) replaces it.
#
# Files changed: shift64-phone-validation.php (header Version + constant),
#                readme.txt (Stable tag + "= X.Y.Z =" changelog entry).
# Usage: ./scripts/update-version.sh <version>
# Works with GNU sed (ubuntu-latest) and BSD sed (macOS): -i always gets an attached suffix.

set -euo pipefail

VERSION="${1:-}"

if [[ ! "$VERSION" =~ ^[0-9]+\.[0-9]+\.[0-9]+(-[0-9A-Za-z.-]+)?(\+[0-9A-Za-z.-]+)?$ ]]; then
	echo "Usage: $0 <version>   (semantic version such as 1.5.0, got '$VERSION')" >&2
	exit 1
fi

cd "$(dirname "$0")/.."

PLUGIN_FILE="shift64-phone-validation.php"

# In-place sed that behaves the same on GNU and BSD sed.
replace() {
	sed -i.bak -e "$1" "$2"
	rm -f "$2.bak"
}

echo "Updating version to: $VERSION"

# Plugin header "Version:".
replace "s/^ \* Version:.*/ * Version:         $VERSION/" "$PLUGIN_FILE"

# SHIFT64_PHONE_VALIDATION_VERSION constant.
replace "s/define( 'SHIFT64_PHONE_VALIDATION_VERSION', '[^']*' );/define( 'SHIFT64_PHONE_VALIDATION_VERSION', '$VERSION' );/" "$PLUGIN_FILE"

# readme.txt "Stable tag:".
replace "s/^Stable tag: .*/Stable tag: $VERSION/" readme.txt

# readme.txt "== Changelog ==": prepend the releases CHANGELOG.md has and the readme lacks.
php scripts/sync-readme-changelog.php

# Fail the release before anything is committed or tagged if a value was not written.
check() {
	if ! grep -q "$@"; then
		echo "update-version.sh: expected value not written: grep $*" >&2
		exit 1
	fi
}
check -xF " * Version:         $VERSION" "$PLUGIN_FILE"
check -F "define( 'SHIFT64_PHONE_VALIDATION_VERSION', '$VERSION' );" "$PLUGIN_FILE"
check -xF "Stable tag: $VERSION" readme.txt
# Only the Changelog section counts: "= X.Y.Z =" may also head an Upgrade Notice entry.
changelog="$(awk '/^== Changelog ==/ { in_section = 1; next } /^== .* ==/ { in_section = 0 } in_section' readme.txt)"
if ! grep -qxF "= $VERSION =" <<< "$changelog"; then
	echo "update-version.sh: readme.txt '== Changelog ==' has no '= $VERSION =' entry" >&2
	exit 1
fi

echo "Version $VERSION written to the plugin header, the constant, the readme Stable tag and the readme changelog."
