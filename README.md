# SHIFT64 Phone Validation for WooCommerce

Phone number validation and formatting for WooCommerce checkout, powered by Google's [libphonenumber](https://github.com/giggsey/libphonenumber-for-php-lite) (v9).

The plugin checks billing and shipping phone numbers when an order is placed, rejects numbers that are not real for the given country, and (optionally) rewrites valid numbers into one consistent format before they are stored on the order. It validates numbers only: it does not send SMS messages or one-time codes and does not confirm that a number belongs to the customer.

This README is the developer documentation. The WordPress.org listing text lives in [`readme.txt`](readme.txt).

## Features

- Validates **billing** and **shipping** phone numbers (the shipping phone only when filled in; whether a phone is required stays a WooCommerce setting, which WooCommerce applies to the shipping phone too)
- Works with both **classic checkout** (shortcode) and **block checkout** (Store API)
- Uses the address country as parsing context – `600 123 456` with country `PL` is understood as `+48600123456`
- Input normalization: spaces (including non-breaking ones pasted from documents), hyphens, dots, slashes and parentheses are stripped, a leading `00` is treated as `+` (typographic dashes such as `–` are rejected by WooCommerce's own `WC_Validation::is_phone()` before the plugin runs)
- Two validation modes: *default country + international* or *international only* (every number must carry its country prefix, typed with `+` or `00`)
- Output formats: E.164 (`+48600123456`), international (`+48 600 123 456`), national (`600 123 456`)
- Highlights the offending phone field on checkout (small JS helpers, no build step; can be switched off for themes with their own error UX)
- Translations: English and Polish; on multilingual stores (Polylang, WPML) block-checkout messages follow the shopper's language
- No external requests: validation runs on the server with the rules bundled in `vendor/`

## Requirements

- WordPress 7.1+ (on an older version the plugin checks nothing and shows administrators a notice naming the required version)
- WooCommerce 7.2+ (declared compatible with High-Performance Order Storage and Cart & Checkout blocks)
- PHP 8.3+

## Installation

**From WordPress.org** (once the plugin is listed): *Plugins → Add New Plugin*, search for "SHIFT64 Phone Validation for WooCommerce", install and activate. Updates then arrive through *Dashboard → Updates* like any other directory plugin.

**From a release ZIP:**

1. Download `shift64-phone-validation.zip` from the [latest release](https://github.com/mateusz-zadorozny/shift64-phone-validation/releases/latest).
2. In WP Admin go to *Plugins → Add New Plugin → Upload Plugin* and upload the file.
3. Activate the plugin.

**Never use GitHub's *Code → Download ZIP* button.** That is a source archive: it has no `vendor/` directory (libphonenumber and the autoloader), and it unpacks into a folder named `shift64-phone-validation-master`. The plugin then only shows an admin notice that its bundled vendor folder is missing and does nothing. The release ZIP contains a production `vendor/` and unpacks into `shift64-phone-validation/`.

If you do run the plugin from a git checkout, install the dependencies inside the plugin directory:

```bash
composer install --no-dev --optimize-autoloader   # runtime only; use plain `composer install` for development
```

### Moving an existing install to WordPress.org updates

Up to 2.0.0 the plugin was called *Verify Phone Number Shift64* and lived in the folder `verify-phone-number-shift64`. WordPress.org asked for a name that starts with its distinctive part, so from 2.0.1 it is *SHIFT64 Phone Validation for WooCommerce*, and the folder, the main file (`shift64-phone-validation.php`) and the text domain are `shift64-phone-validation`. The settings (the five `shift64_phone_validation_*` options) and the phone numbers saved on orders keep their names, so nothing is lost, but WordPress sees the renamed plugin as a different one: an install from before 2.0.1 is not updated in place and has to be replaced once.

Up to 1.4.2 the plugin updated itself from GitHub releases. That updater is removed in 1.5.0 (WordPress.org does not allow plugins to install updates from other servers); from 1.5.0 on, updates come from WordPress.org only. The 1.4.x updater looks for a release asset named `verify-phone-number-shift64.zip`, which releases from 2.0.1 on no longer carry, so it offers nothing newer. On WordPress below 7.1, stay on 1.5.1 (upload its release ZIP): 2.0.0 and later check no phone numbers until WordPress is updated.

WordPress.org only updates a plugin installed in the folder **`shift64-phone-validation`** (verified on 2026-10-06: the live update API, `api.wordpress.org/plugins/update-check/1.1/`, offered an update for `query-monitor/query-monitor.php` but not for the same plugin in `query-monitor-master/` or other renamed folders, and likewise for several other plugins). A copy in any other folder – `verify-phone-number-shift64` or `verify-phone-number-shift64-master` from before the rename, or `shift64-phone-validation-master` from a source archive – gets no directory updates.

To move such a copy, replace it with WP-CLI, which keeps the settings. Deactivate the old copy first: the old and the new copy cannot be active at the same time.

```bash
wp plugin list --fields=name,status,version     # find the folder name
wp plugin deactivate verify-phone-number-shift64-master
wp plugin delete verify-phone-number-shift64-master    # removes the files only, does NOT run uninstall.php
wp plugin install shift64-phone-validation --activate    # from WordPress.org, or pass the path/URL of the release ZIP
```

Do **not** remove the old copy with *Delete* on the WP Admin *Plugins* screen: that runs `uninstall.php`, which deletes the plugin's settings (the five `shift64_phone_validation_*` options). Phone numbers already saved on orders are not affected either way.

## Settings

**WooCommerce → Settings → Phone Validation** tab
(`wp-admin/admin.php?page=wc-settings&tab=phone_validation`)

| Setting | Option name | Default | Description |
| --- | --- | --- | --- |
| Enable Validation | `shift64_phone_validation_enabled` | `yes` | Master switch for validation, formatting, the whitespace clean-up and the checkout scripts |
| Default Country | `shift64_phone_validation_default_country` | `PL` | Fallback only: region used for numbers without `+` **when the address has no country**. See [Country context](#country-context) |
| Validation Mode | `shift64_phone_validation_validation_mode` | `default_and_international` | `international_only` rejects every number without a country prefix (`+` or `00`) |
| Output Format | `shift64_phone_validation_output_format` | `E164` | `E164`, `INTERNATIONAL` or `NATIONAL` |
| Enable Formatting on Save | `shift64_phone_validation_format_on_save` | `yes` | Rewrite valid numbers to the output format before saving the order |

Settings are stored as regular `wp_options` rows. They are kept on deactivation and removed when the plugin is deleted from WP Admin (`uninstall.php`; WP-CLI's `wp plugin delete` skips it). Phone numbers already saved on orders are never touched.

## Country context

A number typed **with** a prefix (`+48…` or `0048…`) is unambiguous and is validated as such, whatever the address says.

A number typed **without** a prefix is read in the country of the billing / shipping address; the *Default Country* setting is used only when the address has no country. The library checks whether the digits form a valid number *in that country* – it cannot know whose number it is:

| Input | Address country | Result |
| --- | --- | --- |
| `600 100 200` | PL | valid → `+48600100200` |
| `600 100 200` | GB, CZ, US | rejected |
| `600 100 200` | DE, FR | **valid** → stored as `+49600100200` / `+33600100200` |

The last row is the limitation to be aware of on stores selling abroad: a customer with a foreign address and a domestic phone must type the prefix. If that is a concern, use the *International only* validation mode (every number must carry its country prefix), or tell customers in the field description to include the prefix. Single-country stores are not affected.

## How it works

```
raw input ──► Normalizer ──► PhoneValidator ──► ValidationResult ──► PhoneFormatter ──► order
"0048 600-123-456"  "+48600123456"   libphonenumber parse + isValidNumber      "+48600123456"
```

| Checkout type | Validation hook | Formatting hook |
| --- | --- | --- |
| Classic | `woocommerce_after_checkout_validation` | `woocommerce_checkout_create_order` |
| Block (Store API) | `woocommerce_store_api_checkout_update_order_from_request`, **POST (place order) only** – throws `RouteException`, HTTP 400 with `field` and error `code` | same hook; WooCommerce saves the order |

Only these checkout hooks are registered, so orders created or edited in the admin and My Account addresses are never validated directly. Two side effects of the block hook: paying for an existing order through the Store API (`POST /wc/store/v1/checkout/{order_id}`) fires the same hook, so the phones sent with that payment are validated and formatted; and after the hook WooCommerce copies the order's (formatted) phones to the customer account of a logged-in customer or one created at checkout. On the classic checkout the customer is saved before the order is created, so the account keeps the phone without the plugin's formatting.

Before either hook runs, WooCommerce itself checks phone fields with `WC_Validation::is_phone()`, whose pattern only knows ASCII whitespace: a number with a non-breaking space (pasted from Word, Outlook or a PDF) would be rejected with WooCommerce's own message before the plugin sees it. `Checkout\WhitespaceFilter` therefore collapses Unicode whitespace to plain spaces first, on `woocommerce_process_checkout_field_billing_phone` / `..._shipping_phone` (classic) and on `rest_pre_dispatch` for `/wc/store/*` requests (Store API). Only the kind of space changes, separators stay.

Code layout:

```
shift64-phone-validation.php   bootstrap, constants, hook registration
uninstall.php                     deletes the plugin's options when it is deleted from WP Admin
src/functions.php                 public helpers (format_phone())
src/Admin/Settings.php            WooCommerce settings tab + option getters
src/Admin/DependencyChecker.php   WordPress version and WooCommerce checks, with their admin notices
src/Validation/                   Normalizer, PhoneValidator, ValidationResult
src/Formatter/PhoneFormatter.php  E.164 / international / national output
src/Checkout/                     classic + block checkout integration, whitespace pre-clean, error messages, asset loading
assets/js/                        field highlighting for both checkout types
languages/                        .pot, en_US, pl_PL
scripts/                          release tooling (version bump, readme changelog sync, package build) – not shipped
.wordpress-org/                   WordPress.org directory icons, banners and screenshots – not shipped
```

## Developers

Validation logic lives in the plugin, presentation can live in the theme. All extension points are WordPress filters, so a theme that uses them keeps working when the plugin is deactivated (an unused `add_filter()` is a no-op).

| Filter | Arguments | Purpose |
| --- | --- | --- |
| `shift64_phone_validation_enqueue_assets` | `bool $enqueue` | Return `false` to skip the highlighting scripts when the theme has its own checkout error UX. Validation is server-side and keeps working. |
| `shift64_phone_validation_error_message` | `string $message, ?string $code, string $field, string $context` | Reword the customer-facing message. `$code`: `missing_international_prefix`, `invalid_number`, `too_short`, `too_long`, `not_a_number`, `invalid_country_code`, `parse_error`, `empty` (input made only of separators). `$field`: `billing` / `shipping`. `$context`: `classic` / `block`. |
| `shift64_phone_validation_should_validate` | `bool $validate, string $field, array\|WC_Order $context` | Return `false` to skip validation **and** formatting for a field in this request (e.g. staff orders); the whitespace clean-up still runs. `$context` is the posted data on classic checkout, the order otherwise. |
| `shift64_phone_validation_formatted_phone` | `string $formatted, PhoneNumber $number, string $field, WC_Order $order` | Change the value stored on the order. `$number` is a `libphonenumber\PhoneNumber`; `PhoneFormatter::format_to()` formats it differently. Runs only while *Enable Formatting on Save* is on. |

```php
// Theme owns the error UX and the wording.
add_filter( 'shift64_phone_validation_enqueue_assets', '__return_false' );
add_filter(
	'shift64_phone_validation_error_message',
	function ( $message, $code ) {
		return 'missing_international_prefix' === $code
			? 'Podaj numer telefonu z prefiksem kraju, np. +48.'
			: 'Podaj poprawny numer telefonu.';
	},
	10,
	2
);
```

An integration that needs a different format than the stored one can use the helper `Shift64\SmartPhoneValidation\format_phone( string $raw_phone, ?string $country_code = null, string $format = 'E164' ): ?string`. It validates the number (with the current *Validation Mode*, so in *International only* mode a number without `+` / `00` returns `null` even when `$country_code` is passed) and returns it in `E164`, `INTERNATIONAL` or `NATIONAL` format (upper case; any other value returns E.164), or `null` when it is not valid; without `$country_code` the *Default Country* setting is used. Guard the call – it only exists while the plugin is active:

```php
if ( function_exists( 'Shift64\\SmartPhoneValidation\\format_phone' ) ) {
	$national = \Shift64\SmartPhoneValidation\format_phone( $order->get_billing_phone(), 'PL', 'NATIONAL' ); // null when invalid.
}
```

Classic checkout errors are added with the field id, which WooCommerce prints on the notice as `data-id="billing_phone"` / `data-id="shipping_phone"`, and every default message contains the word "phone" (pl_PL: "telefon"), so both id-based and keyword-based theme mappers can attach them to the field. Where the attribute sits depends on the notice templates WooCommerce uses, and the highlighting script (`assets/js/checkout-validation.js`) handles both:

- classic themes: on each `<li>` of `ul.woocommerce-error`, one per error;
- block themes, and classic themes that opt in with the `woocommerce_use_block_notices_in_classic_theme` filter (WooCommerce 8.8+): a single error puts it on the `div.wc-block-components-notice-banner.is-error` itself; several errors put it on the `<li>` items inside that banner, which then has no `data-id` of its own.

A `woocommerce/notices/error.php` override replaces these markups, so the field is highlighted only if that template keeps one of these shapes. With block notices, WooCommerce looks for the override only in the active theme's own `woocommerce/` folder (not in a parent theme or a filtered template path).

Block checkout shows Store API errors in a generic banner, so the highlighting script recognises the plugin's notices by their exact text. When the script loads, `shift64_phone_validation_error_message` is applied for every `$code` with `$context` = `block`, and the distinct messages per field are what the script looks for, so a message reworded for any code (for example `too_short`) still highlights the field. Which field is highlighted comes from the place-order response, which names it in the Store API error, so a message reworded without looking at `$field` (the same text for billing and shipping) still highlights the right one; if the response cannot be read, text that fits both fields highlights neither. The script looks for each message anywhere in the banner text, so keep a reworded message specific enough not to appear inside an unrelated notice.

**Formatting on Save is on by default.** Before going live, check every consumer of the order phone (order export, courier labels, SMS gateway): the stored value changes shape, e.g. `600 100 200` → `+48600100200`.

## Development

```bash
composer install      # PHP dependencies + PHPCS tooling
npm ci                # semantic-release and e2e tooling

composer phpcs        # WordPress Coding Standards + PHPCompatibilityWP
composer phpcbf       # auto-fix what can be fixed
```

### Translations

Every user-facing string uses the `shift64-phone-validation` text domain. After changing strings:

```bash
composer makepot      # regenerate languages/shift64-phone-validation.pot (slug pinned, so the folder name does not matter)
composer makepo       # merge the new .pot into the en_US and pl_PL .po files (wp i18n update-po)
# translate the new entries in languages/*.po, then:
composer makemo       # compile the .mo files (wp i18n make-mo)
```

The bundled files are loaded just in time from the folder named by the `Domain Path: /languages` header; a language pack from translate.wordpress.org takes precedence for its locale. WordPress registers that folder itself only for a plugin activated per site, so the main plugin file also registers it with `load_plugin_textdomain()` for network-activated installs (since WordPress 6.7 that call loads no file; Plugin Check reports it as a warning, which is accepted). The block checkout switches to the shopper's locale with `switch_to_locale()` and never unloads the text domain (that left messages in English on WordPress 6.7+, [#32](https://github.com/mateusz-zadorozny/shift64-phone-validation/issues/32)). Once the plugin is listed, translations can also be contributed on translate.wordpress.org.

### Tests

```bash
composer test         # PHPUnit unit suite (tests/Unit), no WordPress required
```

The unit suite covers the pure logic and the checkout integration: `Normalizer`, `PhoneValidator` (including error codes), `PhoneFormatter`, `ValidationResult`, the error-message mapping in English and Polish, the classic and block checkout validators, the filters and the whitespace pre-clean. WordPress is not loaded: WordPress functions are stubbed in `tests/Unit/bootstrap.php`, WooCommerce classes in `tests/Unit/stubs-woocommerce.php`.

#### End-to-end (WooCommerce checkout in a real browser)

The e2e suite drives a real WordPress + WooCommerce store with the plugin active, through both checkout flavours (block / Store API and the classic shortcode). It needs Docker and Node 22, the version CI uses (`.nvmrc` pins it, so `nvm use` or `fnm use` picks it up).

Agents start the store with `sh .ai/scripts/test-env-up.sh` and stop it with `sh .ai/scripts/test-env-down.sh`. On a host where they run as root, these scripts hand themselves over to an unprivileged user, because WP-CLI inside wp-env refuses root. That user is `TEST_ENV_RUN_AS`, `wpenv` by default. Set it up once:

```bash
useradd --system --create-home --shell /bin/bash --groups docker wpenv
setfacl -m u:wpenv:x /root                                      # every parent directory of the checkout
setfacl -R -m u:wpenv:rwX,d:u:wpenv:rwX /path/to/the/checkouts  # d: also covers new worktrees
```

The scripts also install the devDependencies even under a host-wide `NODE_ENV=production`, and use `php8.3` (or newer) when the default `php` is older.

```bash
nvm use
npm ci
npx playwright install chromium
npm run env:start    # composer install + wp-env start; seeds the store on every start
npm run test:e2e     # Playwright against http://localhost:8888
npm run env:stop
```

On macOS with Node 26, `npx playwright install chromium` (Playwright 1.63) fails at once with `Request to https://cdn.playwright.dev/… timed out after 30000ms`, whatever `PLAYWRIGHT_DOWNLOAD_CONNECTION_TIMEOUT` says, although the same URL downloads fine with `curl`. Node 26 on Linux is not affected, and neither is CI. Switch to Node 22 and run the install again. If you must stay on Node 26, run `npx playwright install --dry-run chromium`, which prints the download URL and install location of each browser without downloading anything. Download the Chrome for Testing and Chrome Headless Shell zips with `curl`, unpack each into its install location (`~/Library/Caches/ms-playwright/chromium-<rev>/` and `chromium_headless_shell-<rev>/`), and create empty `INSTALLATION_COMPLETE` and `DEPENDENCIES_VALIDATED` files in each ([#30](https://github.com/mateusz-zadorozny/shift64-phone-validation/issues/30)).

`.wp-env.json` describes the environment (latest WordPress, PHP 8.3, latest WooCommerce, this checkout as the plugin). `tests/e2e/bin/seed.sh` runs after every `wp-env start` and is idempotent: Polish store, guest checkout, cash on delivery, flat-rate shipping, one simple product (`/product/e2e-test-product/`), the block checkout at `/checkout/` (set as the WooCommerce checkout page on every run) and a classic one at `/classic-checkout/`, and the WordPress and WooCommerce `pl_PL` language packs, installed but not activated: the site stays English, and `block-checkout-polish-messages.spec.ts` switches it to Polish and back. It also installs the classic theme Twenty Twenty-One without activating it: `classic-checkout-classic-theme-highlight.spec.ts` switches to it and back, because only a classic theme renders the classic `ul.woocommerce-error` notices. Admin login is wp-env's default (`admin` / `password`).

Each git worktree gets its own containers; set `WP_ENV_PORT` to run several side by side (Playwright reads the same variable, `WP_BASE_URL` overrides it entirely). Tests live in `tests/e2e/*.spec.ts` with shared helpers in `tests/e2e/helpers/`; `.wp-env.json` mounts `tests/e2e/mu-plugins/` as the site's must-use plugins, for fixtures a spec switches on per request with a cookie (`block-checkout-reworded-message-highlight.spec.ts` rewords two error codes that way, the way a theme would); timeouts and retries belong in `playwright.config.ts`, never in a test. Some specs log in to change global settings and restore them afterwards: the `admin-*` specs change plugin settings, `block-checkout-polish-messages.spec.ts` switches the site language, and `classic-checkout-classic-theme-highlight.spec.ts` switches the theme. That is why the suite runs with one worker. These specs read `TEST_ADMIN_USER` / `TEST_ADMIN_PASSWORD` from the environment or from `.ai/qa/test-env.env`, which `sh .ai/scripts/test-env-up.sh` writes (the agent-pipeline entrypoint that wraps `wp-env start` and records the running instance in `.ai/qa/test-env.json`). If a run is killed before it can restore its settings, `npm run env:seed` (or the next `wp-env start`) sets the site language back to English and the default theme back.

### Continuous integration

On every pull request:

- `.github/workflows/code-quality.yml` runs PHPCS and the unit suite, and a **package** job that builds the release package with `scripts/build-package.sh` and runs the official [Plugin Check](https://github.com/WordPress/plugin-check-action) on it, so every PR proves the package has no Plugin Check errors;
- `.github/workflows/e2e.yml` runs the e2e suite. It also runs weekly and on demand, and logs the WordPress and WooCommerce versions it ran against, so a new core or WooCommerce release that breaks the checkout shows up without a code change;
- `.github/workflows/pr-lint.yml` checks the PR title against Conventional Commits.

`tests/bootstrap.php` and `bin/install-wp-tests.sh` are kept for a future WordPress integration suite.

### What ships

`.distignore` is the single source of truth for what goes into the plugin package. It keeps the runtime files – the main plugin file, `uninstall.php`, `src/`, `assets/`, `languages/`, the production `vendor/`, `composer.json`, `readme.txt` and `LICENSE` – and excludes development and process files (this README, `AGENTS.md` and the other process documents, `CHANGELOG.md`, `docs/`, `scripts/`, `tests/`, `bin/`, `.ai/`, `.wordpress-org/`, `.wp-env.json`, test and lint configs, `package*.json`, `composer.lock`, `node_modules/`, hidden files). Anything added at the repository root that is not plugin runtime code belongs in `.distignore`.

Build the package locally (the build script refuses a `vendor/` that still contains dev dependencies):

```bash
composer install --no-dev -o && ./scripts/build-package.sh /tmp/vpn-build && composer install
```

The result is `/tmp/vpn-build/shift64-phone-validation.zip`; the trailing `composer install` restores the dev tooling.

### Releases

Releases are fully automated with [semantic-release](https://semantic-release.gitbook.io/) on every push to `master`:

- Pull requests are squash-merged, so the PR title becomes the commit message and decides the version ([Conventional Commits](https://www.conventionalcommits.org/)): `feat:` → minor, `fix:` / `perf:` → patch, `BREAKING CHANGE` → major, anything else → no release. PR titles are linted.
- semantic-release writes `CHANGELOG.md`, and `scripts/update-version.sh` bumps the plugin header, the `SHIFT64_PHONE_VALIDATION_VERSION` constant and `Stable tag` in `readme.txt`. It also runs `scripts/sync-readme-changelog.php`, which prepends the new version to the `== Changelog ==` section of `readme.txt` from `CHANGELOG.md` and keeps the 10 most recent versions (older ones are linked to `CHANGELOG.md` on GitHub). Never bump versions by hand.
- `scripts/build-package.sh` builds **one** package from the tagged commit. The same folder is used twice: zipped as `shift64-phone-validation.zip` and attached to the GitHub release, and deployed to the WordPress.org SVN repository (trunk + tag).
- The WordPress.org deploy runs automatically once the repository secrets `SVN_USERNAME` and `SVN_PASSWORD` exist; until then the release workflow skips it with a notice.
- `.github/workflows/wporg-deploy.yml` (manual, takes a tag) deploys an existing release to WordPress.org – for the first deploy after the plugin is approved and for retries.
- `.github/workflows/wporg-assets.yml` syncs `readme.txt` and `.wordpress-org/` (icons, banners, screenshots) to WordPress.org without a new version – after each release and on demand, for listing-only changes.
- Not automated: bump `Tested up to` in `readme.txt` (and `WC tested up to` in the plugin header) by hand after checking a new WordPress / WooCommerce major, and write an `== Upgrade Notice ==` entry by hand for releases that need one.

Full walkthrough of the pipeline: [docs/CI-CD-SETUP.md](docs/CI-CD-SETUP.md). Version history: [CHANGELOG.md](CHANGELOG.md).

## Credits

Created and maintained by Mateusz Zadorożny at **SHIFT64**.

Sponsored by SHIFT64 – [Custom WooCommerce Coding](https://shift64.com/services/custom-woocommerce-coding).

## License

GPL-2.0-or-later. The full license text is in [LICENSE](LICENSE).

Bundled libraries (in the release package's `vendor/`): [libphonenumber-for-php-lite](https://github.com/giggsey/libphonenumber-for-php-lite) (Apache-2.0) and [symfony/polyfill-mbstring](https://github.com/symfony/polyfill-mbstring) (MIT). Apache-2.0 is compatible with GPLv3, so the plugin as a whole is distributed under the GPL through the "or later" clause.
