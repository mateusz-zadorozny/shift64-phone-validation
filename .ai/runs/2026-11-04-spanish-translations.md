# Execution plan — Spanish translations

## Goal

Ship bundled Spanish translations for Spain and the main Latin American WordPress locales, so Spanish-speaking stores show the checkout error messages, the settings screen and the admin notices in Spanish.

## Scope

- `languages/shift64-phone-validation-es_ES.po/.mo`: Spain, informal "tú" (as WordPress's own es_ES), "introduce".
- One Latin American translation ("usted", "ingrese": neutral and polite across the region, including Argentina, where "vos" would be wrong anywhere else) shipped as `es_MX`, `es_AR`, `es_CO`, `es_CL`, `es_PE` and `es_VE`. WordPress never falls back from `es_MX` to `es_ES`, so each locale needs its own file.
- Unit tests: the Spanish files are complete, keep placeholders and have their `.mo` compiled (the existing tests, extended to the new locales); every Spanish error message names the phone ("teléfono"); es_ES never uses "usted", the Latin American files never use "tú"; the Latin American files are identical apart from their header.
- Block checkout on multilingual stores: the `/es/` language directory of the referring page resolves to the site's own Spanish locale (`es_MX`, `es_AR`...) or an installed Spanish locale, not only `es_ES`. WPML (`default_locale`) and Polylang (full locale) already give the variant.
- Docs: README, readme.txt, AGENTS.md, CODE_REVIEW.md mention Spanish.

## Non-goals

- Other Spanish locales WordPress offers (`es_UY`, `es_GT`, `es_CR`, `es_PR`, `es_EC`, `es_DO`): they can be added later as copies of the Latin American file.
- Changing any msgid or the text domain.
- Contributing to translate.wordpress.org.

## Implementation Plan

### Phase 1: Translation files

1. Add es_ES translation (`.po` + compiled `.mo`).
2. Add the Latin American translation for es_MX, es_AR, es_CO, es_CL, es_PE, es_VE.
3. Unit test the Spanish translation files.

### Phase 2: Spanish variants on multilingual block checkout

1. Resolve the `/es/` referer to the site's or an installed Spanish locale. Unit tests.

### Phase 3: Docs

1. Document Spanish translations.

## Risks

- Translation quality is machine-authored; a native-speaker review is recommended before release (noted in the PR).
- Widening the referer match from `de_DE_*` variants to the whole language (`es_*`) also lets `/de/` on a `de_AT` site resolve to `de_AT`; that is the intended result, covered by a test.

## Progress

> Convention: `- [ ]` pending, `- [x]` done. Append ` — <commit sha>` when a step lands. Do not rename step titles.

### Phase 1: Translation files

- [ ] 1.1 Add es_ES translation
- [ ] 1.2 Add Latin American Spanish translations
- [ ] 1.3 Unit test the Spanish translation files

### Phase 2: Spanish variants on multilingual block checkout

- [ ] 2.1 Resolve the es referer to the site's or an installed Spanish locale

### Phase 3: Docs

- [ ] 3.1 Document Spanish translations
