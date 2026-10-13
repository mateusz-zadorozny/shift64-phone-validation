#!/usr/bin/env bash
# Decides whether a pull request changes only documentation, so the code checks can be
# skipped (.ai/specs/2026-10-13-skip-ci-for-docs-only-prs.md).
#
# Reads changed paths, one per line, on stdin and prints "true" only when there is at
# least one path and every path is on the allowlist below; otherwise "false". Fail-closed:
# any path the allowlist does not name means the full suite runs.
#
# Not docs, on purpose: readme.txt (ships, Plugin Check validates it), CHANGELOG.md (read by
# tests/Unit/ReadmeChangelogSyncTest.php), tests/ (Markdown fixtures included), LICENSE
# (build-package.sh requires it), any other *.md (a new root one fails the package build),
# languages/, .github/ (a workflow change must prove itself) and .ai/qa/ (playwright.config.ts
# loads .ai/qa/test-env.env). Under docs/ only Markdown counts: .phpcs.xml.dist scans docs/.
#
# Usage: git diff --no-renames --name-only origin/master...HEAD | ./scripts/ci-docs-only.sh

set -euo pipefail

seen=0
while IFS= read -r path || [ -n "$path" ]; do
	[ -z "$path" ] && continue
	seen=1
	case "$path" in
		AGENTS.md | CLAUDE.md | SDLC.md | CODE_REVIEW.md | BACKWARD_COMPATIBILITY.md | README.md) ;;
		# Markdown only: PHPCS scans every PHP file outside its exclude list, docs/ included.
		docs/*.md) ;;
		.ai/qa/*)
			echo false
			exit 0
			;;
		.ai/* | .claude/* | .wordpress-org/*) ;;
		*)
			echo false
			exit 0
			;;
	esac
done

if [ "$seen" -eq 1 ]; then
	echo true
else
	echo false
fi
