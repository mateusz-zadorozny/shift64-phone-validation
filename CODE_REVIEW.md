# Code review

`om-code-review` reads this file automatically and applies it on top of its built-in checklist. It is also the checklist a human reviewer uses. Findings are reported at one of three severities:

- **Blocker** — must be fixed before merge: a correctness bug on a real input, a security hole, an unhandled breaking change, a missing regression test for a fixed bug.
- **Major** — should be fixed in this PR: a real but narrower problem, a missing edge case, a contract documented wrongly.
- **Minor** — a nit. Files as a follow-up issue rather than holding the PR.

## Review priorities

### 1. Correctness at the checkout boundary

This plugin sits between a customer and a placed order. The expensive failure mode is not a crash — it is **rejecting a phone number that is actually valid**, which silently blocks a real sale. Weigh findings accordingly.

- Does the change alter which numbers pass? If yes, the PR needs unit tests naming concrete numbers on both sides of the new boundary.
- Is the country context still taken from the address country rather than assumed? A number valid in one country is invalid in another.
- Is the shipping phone still treated as optional — validated only when non-empty?
- Do classic checkout and block checkout still behave identically? A rule added to one and not the other is a Major at minimum; the two paths are `BillingPhoneValidator`/`ShippingPhoneValidator` and `BlockCheckoutValidator`.
- Is `Settings::is_validation_enabled()` / `is_format_on_save_enabled()` still honored on the new path?

### 2. Contract surfaces

Check the diff against `BACKWARD_COMPATIBILITY.md`. A change to a `shift64_phone_validation_*` filter, an option, the stored order phone, `format_phone()`, an `ERROR_*` code, or a `FORMAT_*` value is a **Blocker** unless the required path (deprecation, migration, changelog note, major-release marker) is in the same PR. Say which surface and which rule.

New option added but not deleted in `uninstall.php` → Blocker. It leaks after uninstall.

### 3. Security and data handling

- Every value that reaches output is escaped (`esc_html`, `esc_attr`, `wp_kses_post`) and every input is sanitized. PHPCS catches most of this; it does not catch all of it.
- Settings writes are capability-checked and nonce-verified.
- No update checker and no outgoing HTTP request may be introduced or reintroduced: no `wp_remote_*` / `wp_safe_remote_*` call and no hook on `pre_set_site_transient_update_plugins`, `plugins_api` or `upgrader_*`. Serving updates from anywhere but WordPress.org breaks Detailed Plugin Guideline 8, which is why the GitHub self-updater was removed (D05) → Blocker.
- Phone numbers are personal data. They do not belong in logs, error messages shown to other users, or debug output.

### 4. WordPress and WooCommerce conventions

- Orders are touched only through `WC_Order` CRUD methods. Direct post-meta access breaks HPOS → Blocker.
- Hook callbacks keep the signature WooCommerce passes, even when a parameter is unused.
- Every user-facing string is translated with the `shift64-phone-validation` text domain, and strings changed in a PR are reflected by `composer makepot` plus updated `languages/*.po`/`*.mo`.
- Block checkout error messages resolve language through Polylang/WPML, not the site locale.
- Globals, functions, and constants carry the `shift64`/`Shift64`/`SHIFT64` prefix (PHPCS enforces it).

### 5. Code structure

- Validation logic belongs in `src/Validation/`, formatting in `src/Formatter/`, WordPress wiring in `src/Checkout/` and `src/Admin/`. Parsing logic appearing inside a checkout hook is a Major.
- libphonenumber is reached only through `PhoneValidator`/`PhoneFormatter` — no direct `libphonenumber\` use in checkout or admin code.
- Input cleanup belongs in `Normalizer`, not in callers.
- Validation failures travel as a `ValidationResult`, not as exceptions or bare booleans.

### 6. Tests

- Every bug fix ships a regression test that fails without the fix.
- Tests run without WordPress: new WordPress functions are stubbed in `tests/Unit/bootstrap.php`, new WooCommerce classes in `tests/Unit/stubs-woocommerce.php`.
- A change to the validation or formatting rules without a test asserting the new behavior is a Blocker.

### 7. Packaging and release

- Anything new at the repo root that is not plugin runtime code is added to `.distignore`.
- Version numbers are never edited by hand — `scripts/update-version.sh` is driven by semantic-release, and it also adds each release's entry to the `readme.txt` changelog.
- The `package` job in `code-quality.yml` builds the release package and runs Plugin Check on it. It must stay at 0 errors: a new error is a Blocker, including one caused only by a development file that now ships in the package (the fix is `.distignore`, not the check).
- The PR title is a valid Conventional Commit and describes the change accurately: it is squashed into the commit that decides the next release. A `feat:` title on a bug fix ships a wrong version number. A breaking change needs a `BREAKING CHANGE:` footer in the squash commit message; a `feat!:` title cuts no release here.

## Validation gate

A review cannot approve without the gate having run:

- `composer install --prefer-dist --no-interaction`
- `composer phpcs`
- `composer test`

A failing check is collected as a Blocker finding and reported together with the full review, never instead of it.
