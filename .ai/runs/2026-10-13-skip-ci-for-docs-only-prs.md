# Execution plan: skip code CI on docs-only pull requests

Source doc: .ai/specs/2026-10-13-skip-ci-for-docs-only-prs.md
Refs: #45 (spec PR, merged)

## Goal

Pull requests that change only allowlisted documentation paths report PHPCS, PHPUnit, Package + Plugin Check and E2E as **Skipped**; everything else, plus every push/schedule/dispatch run, keeps the full suite.

## Scope

- New `scripts/ci-docs-only.sh` (path allowlist classifier, fail-closed).
- New `changes` job in `.github/workflows/code-quality.yml` and `.github/workflows/e2e.yml`; existing jobs gated on it.
- PHPUnit coverage: classifier table test + workflow wiring assertion.
- Docs: `docs/CI-CD-SETUP.md` section, `AGENTS.md` routing row.

## Non-goals

- Spec Phase 2 (verifying the skip on a real docs PR and how `om-*` skills treat `SKIPPED` checks) can only run after this PR merges, because this PR touches `.github/` and therefore runs the full suite. It is a post-merge follow-up, not a step of this run.
- No change to `pr-lint.yml`, `release.yml`, `wporg-*.yml`, plugin runtime code, or branch protection.

## Implementation Plan

### Phase 1: Classifier and wiring

1. Add `scripts/ci-docs-only.sh`.
2. Add `tests/Unit/CiDocsOnlyClassifierTest.php` (table-driven classifier cases).
3. Wire `code-quality.yml` (`changes` job; gate `phpcs`, `phpunit`, `package`).
4. Wire `e2e.yml` (`changes` job; gate `playwright`).
5. Add workflow wiring assertions to the classifier test.
6. Document in `docs/CI-CD-SETUP.md` and `AGENTS.md`.

## Risks

- A path wrongly classified as docs skips real checks → exact-path allowlist, fail-closed on errors, pushes to `master` always run everything.
- The test shells out to bash → available on the CI runners (ubuntu) and macOS; the test skips itself if `bash` is missing.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Classifier and wiring

- [ ] 1.1 Add the docs-only classifier script
- [ ] 1.2 Add classifier unit tests
- [ ] 1.3 Wire code-quality.yml
- [ ] 1.4 Wire e2e.yml
- [ ] 1.5 Add workflow wiring assertions
- [ ] 1.6 Document the docs-only skip
