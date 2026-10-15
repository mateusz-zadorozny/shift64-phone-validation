# Execution plan — scenario demo multisite

Source doc: .ai/specs/2026-10-15-scenario-demo-multisite.md
Brief: .ai/specs/briefs/2026-10-15-scenario-demo-multisite.md

## Goal

An opt-in second wp-env profile (`npm run demo:start`): a subdirectory multisite on `localhost:8890` with WooCommerce and the plugin network-activated, one subsite per scenario with a landing page and a one-click path to checkout, plus a Playwright `demo` project and a `playwright-multisite` CI job that covers the network-activated path.

## Scope

- New: `.wp-env.demo.json`, `tests/e2e/demo/**` (scripts, mu-plugin, scenario file, smoke spec, path helper).
- Changed: `package.json` scripts, `playwright.config.ts` (projects), `.github/workflows/e2e.yml` (new job), `README.md`, `docs/CI-CD-SETUP.md`, `AGENTS.md` Tests row.

## Non-goals

- `.wp-env.json`, `tests/e2e/bin/seed.sh`, the existing specs and helpers, `.ai/scripts/test-env-*.sh` stay unchanged.
- No plugin runtime code, no package content change (`.distignore` already excludes `.*` and `/tests`).
- No "just-local" scenario (the plugin has no such mode; spec Q1).

## Decision: one implementation PR

The spec's Phasing suggests two PRs. The pipeline contract is one implementation PR per spec, so both phases ship here as separate commits; phase 1 is still usable on its own.

## Implementation Plan

### Phase 1: demo network

1. `.wp-env.demo.json`, `tests/e2e/demo/bin/demo.sh` (root handoff, php guard, start|stop|destroy) and `demo:*` npm scripts.
2. `scenarios.php`, `seed-site.php` and `tests/e2e/demo/bin/seed.sh` (languages, theme, network activation, sites, per-site seed).
3. `shift64-demo.php` mu-plugin and `landing.php`: front-page template for scenarios and the index, `?shift64_demo=buy` endpoint.
4. README "Demo network" section.

### Phase 2: multisite smoke in CI

5. Playwright `projects` split (`default` + `demo` under `WP_DEMO=1`) and `tests/e2e/demo/site.ts`.
6. `tests/e2e/demo/multisite-smoke.spec.ts`.
7. `playwright-multisite` job in `e2e.yml`; docs in `docs/CI-CD-SETUP.md` and `AGENTS.md`.

## Risks

- WooCommerce first-boot DB errors on new subsites are transient (spike); the seed and spec ignore the debug log.
- The store baseline is duplicated in `seed-site.php` and `tests/e2e/bin/seed.sh`; both files carry a comment saying so.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: demo network

- [ ] 1.1 Demo wp-env config, demo.sh entrypoint and npm scripts
- [ ] 1.2 Scenario file, per-site seed and network seed script
- [ ] 1.3 Landing mu-plugin and buy endpoint
- [ ] 1.4 README Demo network section

### Phase 2: multisite smoke in CI

- [ ] 2.1 Playwright projects split and sitePath helper
- [ ] 2.2 Multisite smoke spec
- [ ] 2.3 CI job and docs
