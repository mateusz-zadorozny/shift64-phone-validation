# Execution plan — German and formal German translations

## Goal

Ship bundled German translations in both WordPress variants — `de_DE` (informal, "du") and `de_DE_formal` (formal, "Sie") — so German stores show the checkout error messages, the settings screen and the admin notices in German.

## Scope

- New `languages/shift64-phone-validation-de_DE.po/.mo` and `languages/shift64-phone-validation-de_DE_formal.po/.mo`, complete against the current `.pot` (every msgid translated).
- Block checkout on multilingual stores: a store whose German is the formal variant gets formal messages.
  - WPML: the current language's configured `default_locale` (e.g. `de_DE_formal`) is used instead of the fixed code-to-locale map.
  - Referring-page fallback (`/de/` directory): `de` resolves to the installed German variant, preferring the site's own locale when it is one of them.
  - Polylang already returns the full locale (`pll_current_language( 'locale' )`), no change.
- Unit tests: translation-file completeness and placeholder parity for every shipped `.po`, `.mo` compiled from the `.po`, register checks (formal has no "du/dein", informal has no "Sie/Ihr" address), every German error message names the phone ("Telefon"); locale resolution for WPML and the `de` referer.
- Docs: README, readme.txt, AGENTS.md mention German/formal German.

## Non-goals

- Other German variants (`de_AT`, `de_CH`, `de_CH_informal`) — WordPress falls back to none of them automatically; they can be added later from the same files.
- Changing any msgid (English source strings) or the text domain.
- Contributing to translate.wordpress.org (a language pack from there still wins over the bundled file — D07 note in AGENTS.md).

## Implementation Plan

### Phase 1: Translation files

1. Add `de_DE` (informal) `.po` + compiled `.mo`.
2. Add `de_DE_formal` `.po` + compiled `.mo`.
3. Unit test over every shipped `.po`: complete, placeholders preserved, `.mo` in sync, register rules, German error messages mention "Telefon".

### Phase 2: Formal German on multilingual block checkout

1. WPML: use the current language's `default_locale`; referer: resolve `de` to the installed variant, site locale first. Unit tests.

### Phase 3: Docs

1. README, readme.txt, AGENTS.md.

## Risks

- Translation quality is machine-authored; a native-speaker review is recommended before release (noted in the PR).
- WPML `wpml_active_languages` shape is taken from WPML's documented hook API, not exercised against a live WPML install (none available; Q02 in the product brief already records that WPML is unverified in a browser).

## Progress

PR: #60

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Translation files

- [x] 1.1 Add de_DE informal translation — 61a8c9c
- [x] 1.2 Add de_DE_formal translation — 0ad5684
- [x] 1.3 Unit test the shipped translation files — 16603c1

### Phase 2: Formal German on multilingual block checkout

- [x] 2.1 Resolve WPML and referer German to the configured variant — f1f78fe

### Phase 3: Docs

- [x] 3.1 Document German and formal German — 7a006ed
