# Scenario demo multisite: one subsite per language/settings scenario, with a one-click path to checkout

- Date: 2026-10-15
- Category: feature
- Priority signal: medium — no way today to show or QA the plugin's scenarios side by side, and multisite (where #38 and the network-activation textdomain workaround came from) has no e2e coverage
- Risk signal: medium — wp-env multisite on a non-standard port and WooCommerce seeded per subsite are both unproven in this repo
- Routing: Next: om-auto-write-spec "Scenario demo multisite: a second wp-env profile (network-activated subdirectory multisite, one subsite per scenario with an add-to-cart→checkout landing page linking sibling scenarios) plus a multisite e2e smoke job — brief: .ai/specs/briefs/2026-10-15-scenario-demo-multisite.md"

## Problem

The maintainer wants to spin up a quick demo and to improve the default test environment. Today there is one single-site wp-env instance (`.wp-env.json`, port 8888, seeded by `tests/e2e/bin/seed.sh`). Seeing the plugin in a different scenario (Polish, local-numbers-only, national output format, classic theme, classic checkout, validation off) means changing global state by hand, and the e2e specs that need such a scenario flip it and flip it back. There is no place where the scenarios sit side by side for a client demo, screenshots or agent QA. Multisite is not exercised at all, though both past multisite bugs (#38 network-admin dependency notices; `load_plugin_textdomain()` being needed when network-activated) only show up when the plugin is network-activated.

## Agreed direction

The maintainer proposed one subsite per scenario, served from a folder (`/pl/`, `/just-local/` …). Each subsite opens on a page with a big "Add to cart → checkout" button, with links to the other scenarios below it.

Pursue that as a **separate demo profile**. Leave the default environment as it is:

1. **Second wp-env profile** behind `npm run demo:start` / `demo:stop` / `demo:destroy` (names are the spec's call). It is a subdirectory multisite on its own port (8890 suggested), so it can run alongside the default env on 8888. wp-env reads `.wp-env.json` from its working directory, so a sibling directory with its own config is one way to do this; the spec picks the mechanism.
2. **Network-activated** plugin and WooCommerce, so the demo exercises the multisite paths that broke before.
3. **One scenario data file** drives both the seeding and the landing-page links. Use a format the POSIX `sh` seed and a PHP mu-plugin can both read: a PHP array file read through `wp eval-file`, or JSON.
4. **Scenario landing page**, delivered as an mu-plugin under `tests/e2e/mu-plugins` or a demo-only mapping, never shipped in the package. Each subsite's front page shows the scenario name and a one-line description of what to try. The big button adds the seeded product and lands on checkout (block checkout, or the classic page for the classic scenario). Below it are links to every sibling scenario and to the network root.
5. **Multisite e2e smoke job** against the demo profile: one invalid-phone checkout on `/pl/` showing the Polish message, one valid checkout on another subsite, and no dependency notice on Network Admin. It runs in `.github/workflows/e2e.yml` as a separate job or project; the single-site suite stays the main gate.

Rejected:
- **Turning the default env into the multisite and moving all specs to scenario subsites (the original framing).** Single-site is what nearly every store runs and the only setup CI has proven. Moving the specs means rewriting every helper that uses absolute paths (`/wp-login.php`, `/wp-admin/...`). Only about 3 of the 14 spec files change global state, and nobody has measured the serial suite time. This becomes a follow-up item, decided once the demo profile proves stable and the suite has been timed.
- **A WordPress Playground blueprint / WordPress.org Live Preview.** It serves a different audience (the public, from the plugin directory page) and is a different artifact. Parked as its own item.
- **Build nothing.** That keeps hand-flipping state for every demo and leaves multisite untested. Fine for the specs, but it doesn't meet the demo need.

## Resolved unknowns

| Question | Answer (from the conversation) |
|----------|--------------------------------|
| Who is the demo for? | Assumed: the maintainer, locally (client demos, screenshots, recordings, agent QA). The maintainer handed off without answering, so this is an assumption to confirm on the spec PR. A public demo is the parked Playground item. |
| Does the default test env become multisite? | No. `.wp-env.json` stays single-site on 8888 and the existing specs stay as they are. The demo is a second profile. |
| Network-activated or per-site? | Network-activated (plugin and WooCommerce). The smoke job checks the PL message and that no notice appears on Network Admin. |
| Subdomain or subdirectory? | Subdirectory (`localhost:<port>/pl/`). |
| Which scenarios? | Starting set, refined by the spec: English default (E.164, default + international); Polish (`pl_PL` site language, PL messages); just-local (national numbers only); national output format; international output format; classic theme (`twentytwentyone`) with the classic checkout page; validation disabled. The network root is an index page of all scenarios. |
| How is a scenario defined? | One data file: slug, title, description, site language, theme, checkout type, plugin option values. Seed and landing page both read it, so adding a scenario is one entry. |
| Must the spec prove feasibility first? | Yes. Before committing to the design, run a spike: wp-env `multisite` on a non-80 port (login and cookies on a subsite); WooCommerce seeded on a subsite via `wp --url=…` (product, COD, flat-rate shipping, coming-soon off, exactly one `checkout` page); one block-checkout order placed on `/pl/checkout/`. If the port blocks it, the spec records the workaround or the fallback. |
| Single-site coverage? | Unchanged. The full existing suite keeps running on the single-site env and stays the release gate. |

## Non-goals

- Changing `.wp-env.json`, `seed.sh` behaviour on the default env, or migrating existing specs to subsites.
- Parallelising the e2e suite (follow-up, gated on measured suite time).
- A public demo, Playground blueprint or WordPress.org Live Preview (separate item).
- Anything shipped in the plugin package: demo code stays in dev-only paths covered by `.distignore`.
- Per-site-activation coverage on multisite.

## Affected areas (if known)

- New demo wp-env profile and seed script, alongside `.wp-env.json` and `tests/e2e/bin/seed.sh`
- `tests/e2e/mu-plugins/` (or a demo-only mu-plugins mapping) for the landing page
- `package.json` scripts; `.github/workflows/e2e.yml` for the multisite smoke job; `playwright.config.ts` (a separate project with its own `baseURL`)
- `.distignore` and `scripts/ci-docs-only.sh`, if the new paths need listing
- Agent QA consumers that assume one site on :8888 (`.ai/scripts/test-env-up.sh`, the `om-prepare-test-env` descriptor, `.ai/qa/test-env.env`) must keep working unchanged. A demo entry for them is optional.
- `docs/` or `README.md`: how to start the demo
