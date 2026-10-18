# Settings link on the Plugins screen and redirect to settings after activation

## Goal

Get a store owner to the plugin's settings in one click: a *Settings* link under the plugin's row on the WP Admin *Plugins* screen, and a one-time redirect to the settings tab right after the plugin is activated, within the WordPress.org plugin guidelines.

## Scope

- `src/Admin/PluginScreen.php` (new): the action link and the activation redirect.
- `shift64-phone-validation.php`: register the activation hook and boot `PluginScreen` with the rest of the admin code (after the WordPress / WooCommerce checks).
- `uninstall.php`: delete the short-lived redirect transient.
- `languages/`: the new "Settings" string (pot, pl_PL po/mo).
- `readme.txt` / `README.md`: mention the link and the redirect where the settings location is documented.
- Unit tests for both behaviours and for the main-file wiring.

## Non-goals

- No admin notice, welcome screen, or "rate us" prompt.
- No new public filter or option (the redirect needs only a 30-second transient).
- No change to the settings tab itself.

## WordPress.org guidelines

Guideline 11 ("Plugins should not hijack the admin dashboard") limits prompts and redirects. An activation redirect to the plugin's own settings is accepted practice when it happens once, only for the user who activated the plugin, and never during bulk activation, network activation, WP-CLI or AJAX. The implementation honours each of those conditions; the redirect goes only to the plugin's own WooCommerce settings tab.

## Implementation Plan

### Phase 1: Plugins screen

- 1.1 Settings action link (`plugin_action_links_{basename}`), shown to users who can `manage_woocommerce`, with unit tests.
- 1.2 One-time activation redirect: the activation hook sets a 30-second transient (skipped for network-wide activation and WP-CLI); `admin_init` consumes it and redirects to the settings tab unless it is a bulk activation, network admin, AJAX request, or the user cannot `manage_woocommerce`. Wire it in the main file, clean the transient in `uninstall.php`, unit tests including main-file wiring.

### Phase 2: Translations and docs

- 2.1 Regenerate the pot, update and compile the pl_PL translation.
- 2.2 Document the link and the redirect in `readme.txt` and `README.md`.

## Risks

- WordPress.org review could still object to the redirect; it is isolated in one method and can be dropped without touching the link.
- Open PR #60 (German translations) edits the same `.po` files; whichever merges second regenerates them.

## Review fixes

- `om-auto-review-pr` (Major): the redirect fired on whatever admin page loaded next, so an AJAX (Add Plugins card) or REST activation could take over an unrelated page or a form post. Now only `plugins.php?activate=true` redirects, and AJAX/REST activations ask for none — c2234fb.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Plugins screen

- [x] 1.1 Settings action link — 633d859
- [x] 1.2 One-time activation redirect — 633d859

### Phase 2: Translations and docs

- [x] 2.1 Regenerate the pot, update and compile the pl_PL translation — 8ef1780
- [x] 2.2 Document the link and the redirect — 707e613
