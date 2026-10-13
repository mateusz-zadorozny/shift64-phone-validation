# Execution plan — PHPStan static analysis

Source doc: .ai/specs/2026-10-13-phpstan-static-analysis.md
Engine: om-auto-create-pr (steps: 6, --loop: no)

## Goal

Add PHPStan at level 6 (no baseline) to the local validation gate, PR CI (`code-quality.yml`) and as a hard gate before `release.yml` tags anything.

## Scope

- `composer.json` / `composer.lock`: PHPStan, WordPress extension, WooCommerce stubs, extension installer; `phpstan` script.
- `phpstan.neon.dist`, `tests/phpstan/bootstrap.php` (new).
- Docblock-only array value types in `src/Admin/Settings.php`, `src/Checkout/BillingPhoneValidator.php`, `src/Checkout/ShippingPhoneValidator.php`.
- `.github/workflows/code-quality.yml` (`phpstan` job), `.github/workflows/release.yml` (`static-analysis` job, `release` needs it).
- Gate docs: `.ai/agentic.config.json`, `AGENTS.md`, `SDLC.md`, `docs/CI-CD-SETUP.md`; `.distignore` / `.gitignore` hygiene.

## Non-goals

- Level 8+, analysing `tests/`, gating the release on PHPCS/PHPUnit, branch protection (spec Phasing).
- Any runtime behavior change.

## Implementation Plan

### Phase 1: PHPStan level 6 in gate, PR CI and release

1. Add the dependencies and the script.
2. Add the config at level 5 (+ analysis bootstrap).
3. Fix the 8 iterable docblocks and raise to level 6.
4. Add the PR CI job.
5. Add the release gate.
6. Update the gate documentation and packaging hygiene.

## Risks

- A red PHPStan on `master` holds back a release; rollback is removing `needs: static-analysis`.
- Step 4's throwaway "go red" commit is not done: a force-push on a shared PR branch is avoided; the failing-annotation path is verified locally with `--error-format=github` instead.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: PHPStan level 6 in gate, PR CI and release

- [x] 1.1 Add the dependencies and the script — 0bfd528
- [x] 1.2 Add the config at level 5 — ce0e99a
- [x] 1.3 Fix the 8 iterable docblocks and raise to level 6 — fbc34be
- [ ] 1.4 Add the PR CI job
- [ ] 1.5 Add the release gate
- [ ] 1.6 Update the gate documentation and packaging hygiene
