# [2.0.0](https://github.com/mateusz-zadorozny/shift64-phone-validation/compare/v1.5.0...v2.0.0) (2026-11-02)


### Bug Fixes

* **block-checkout:** show the error next to the field, fixes [#41](https://github.com/mateusz-zadorozny/shift64-phone-validation/issues/41) ([1111111](https://github.com/mateusz-zadorozny/shift64-phone-validation/commit/1111111000000000000000000000000000000000))


### Code Refactoring

* **validation:** split the validator into smaller classes ([2222222](https://github.com/mateusz-zadorozny/shift64-phone-validation/commit/2222222000000000000000000000000000000000))


### Features

* **settings:** add a per-country output format, thanks [@contributor](https://github.com/contributor) ([#42](https://github.com/mateusz-zadorozny/shift64-phone-validation/issues/42)) ([3333333](https://github.com/mateusz-zadorozny/shift64-phone-validation/commit/3333333000000000000000000000000000000000))


### Performance Improvements

* load the metadata only on the checkout page ([4444444](https://github.com/mateusz-zadorozny/shift64-phone-validation/commit/4444444000000000000000000000000000000000))


### Reverts

* **checkout:** guess the country from the browser language ([5555555](https://github.com/mateusz-zadorozny/shift64-phone-validation/commit/5555555000000000000000000000000000000000))


### BREAKING CHANGES

* **settings:** the shift64_phone_validation_format option
now stores one format per country

* **ci:** the release job needs the SVN secrets

## [1.5.0](https://github.com/mateusz-zadorozny/shift64-phone-validation/compare/v1.4.2...v1.5.0) (2026-10-07)


### Features

* prepare the plugin for the plugin directory ([#31](https://github.com/mateusz-zadorozny/shift64-phone-validation/issues/31)) ([ddddddd](https://github.com/mateusz-zadorozny/shift64-phone-validation/commit/ddddddd0000000000000000000000000000000000))
